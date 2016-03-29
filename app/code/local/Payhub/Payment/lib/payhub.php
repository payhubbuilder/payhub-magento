<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////

namespace Payhub;

/////////////////////////////////////////////////////////////////////////////////////////////////////

class Payhub
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static $DATETIME_ZONE;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public $standard_request;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private $_oauth_token;

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct($config) { 
        $this->url = get_and_require($config, 'url');
        $this->url = rtrim($this->url, '/\\').'/';

        $mode = get_and_require($config, 'mode');
        if ($mode === 'live') { 
            $this->_oauth_token = get_and_require($config, 'oauth_token');

            $this->standard_request = array(
                'merchant' => array(
                    'organization_id' => get_and_require($config, 'orgid'),
                    'terminal_id' => get_and_require($config, 'tid'),
                ),
            );
        } else if ($mode === 'demo') { 
            $this->_oauth_token = '039d423b-3d4d-4abc-a37f-9de10a07e9ee';

            $this->standard_request = array(
                'merchant' => array(
                    'organization_id' => '10057',
                    'terminal_id' => '101',
                ),
            );
        } else { 
            throw new CustomException('Invalid Payhub mode: ', $mode);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function sale($data, $options = null) { 
        $options['add_response_header'] = true;

        $response = $this->_postResponse('sale', $this->_prepare_data($data), $options);

        $response = array(
            'transaction_id' => get_or_null($response, 'saleResponse', 'saleId'),
            'transaction_date_time' => $this->toLocaltime(get_or_null($response, 'createdAt')),
            'approval_code' => get_or_null($response, 'saleResponse', 'approvalCode'),
            'card_id' => get_or_null($response, 'card_data', 'id'),
            'batch_id' => get_or_null($response, 'saleResponse', 'batchId'),
            'customer_id' => get_or_null($response, 'customer', 'id'),
        );

        return $response;
    }

    public function void($transaction_id, $options = null) { 
        require_value($transaction_id, 'Transaction ID');

        $options['add_response_header'] = true;

        $response = $this->_postResponse('void', array(
            'transaction_id' => $transaction_id
        ), $options);

        $response = array(
            'transaction_id' => get_or_null($response, 'lastVoidResponse', 'voidTransactionId'),
            'parent_transaction_id' => $transaction_id,
            'transaction_date_time' => $this->toLocaltime(get_and_require($response, 'createdAt')),
            'card_token_no' => get_or_null($response, 'lastVoidResponse', 'token'),
        );

        return $response;
    }

    public function refund($transaction_id, $options = null) { 
        require_value($transaction_id, 'Transaction ID');

        $options['add_response_header'] = true;

        $response = $this->_postResponse('refund', array(
            'transaction_id' => $transaction_id,
            'record_format' => 'CREDIT_CARD',
        ), $options);

        $response = array(
            'transaction_id' => get_or_null($response, 'lastRefundResponse', 'refundTransactionId'),
            'parent_transaction_id' => $transaction_id,
            'transaction_date_time' => $this->toLocaltime(get_and_require($response, 'createdAt')),
            'card_token_no' => get_or_null($response, 'lastRefundResponse', 'token'),
        );

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function info($url_hint, $options = null) { 
        return $this->_getResponse($url_hint, null, $options);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    private function _prepare_data(& $data) { 
        return $data;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function toLocaltime($date_time) { 
        if (!$date_time) { 
            return $date_time;
        }
        $store_timezone = \Mage::app()->getStore()->getConfig('general/locale/timezone');
        if ($store_timezone !== self::$DATETIME_ZONE->getName()) { // converting timezone A to timezone A might add an offset in minutes
            $date_time = new \DateTime($date_time, new \DateTimeZone('UTC'));

            $gmt_offset = timezone_offset_get(self::$DATETIME_ZONE, $date_time);

            $date_time = $date_time->modify(strval(abs($gmt_offset)) . 'sec');

            $date_time = \Mage::getModel('core/date')->date(null, $date_time);
        }

        return $date_time;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private function _postResponse($action, $request, $options = null) { 
        require_value($action, 'API action');

        $url = $this->url.'api/v2/'.$action;

        $request = array_merge($request, $this->standard_request);

        $response = null;
        $ex = null;

        Log::network("|payhub||REQUEST||$action| ", $request);

        $add_response_header = isset($options['add_response_header']) && $options['add_response_header'];

        try { 
            $standard_options = array(
                'curlopt' => array(
                    CURLOPT_SSL_VERIFYPEER => 0, 
                ),
                'headers' => array(
                    'Authorization' => 'Bearer'.$this->_oauth_token,
                ),
            );
            if ($add_response_header) { 
                $standard_options['curlopt'][CURLOPT_HEADER] = 1;
            }
            $options = array_merge($standard_options, is_null($options) ? array() : $options);

            if (get_or_null($options, 'post_form')) { 
                $response = CURL::post($url, $request, array_merge($standard_options, is_null($options) ? array() : $options));
            } else { 
                $response = CURL::post_json($url, $request, array_merge($standard_options, is_null($options) ? array() : $options));
            }
        } catch(\Exception $ex) { 
            Log::ex($ex, "|payhub||$action|");

            $response = null;
        }

        Log::network("|payhub||RESPONSE||$action| ", $response);

        if (is_null($response)) { 
            if ($ex && $add_response_header) { 
                $pos = strpos($ex->getMessage(), "\r\n\r\n");
                $pos = $pos !== false ? $pos : strpos($ex->getMessage(), "\n\n");
                $message = trim(substr($ex->getMessage(), $pos));
            } else if ($ex) { 
                $message = $ex->getMessage();
            } else { 
                $message = 'unknown error';
            }
            $match = preg_match('/reason:([^,$]*)[$,].*/', $message, $matches);
            if ($match === 1) { 
                $message = $matches[1];
            }
            throw new CustomException('API request to payhub returned with errors: ', $message);
        }

        $location = trim(preg_replace('/.*Location:\s*([^\n]*).*/ms', '$1', $response));

        if ($location) { 
            $response = $this->_getResponse($location, null, $options);
        }

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private function _getResponse($url_hint, $request = null, $options = null) { 
        if (stripos($url_hint, 'http') === 0) { 
            $url = $url_hint;
        } else { 
            $url = $this->url.'api/v2/'.$url_hint;
        }

        $response = null;
        $ex = null;

        Log::network("|payhub||REQUEST||$url_hint| ", $request);

        try { 
            $response = CURL::get($url, $request, array_merge(array(
                'curlopt' => array(
                    CURLOPT_SSL_VERIFYPEER => 0, 
                    CURLOPT_HEADER => 0,
                ),
                'headers' => array(
                    'Authorization' => 'Bearer'.$this->_oauth_token,
                ),
            ), is_null($options) ? array() : $options));
        } catch(\Exception $ex) { 
            Log::ex($ex, "|payhub||$url_hint|");

            $response = null;
        }

        if ($response && strpos($response, 'HTTP/') == 0) { 
            $pos = strpos($response, "\r\n\r\n");
            $pos = $pos !== false ? $pos : strpos($response, "\n\n");
            $response = trim(substr($response, $pos));
            $response = json_decode($response, true);
        }

        Log::network("|payhub||RESPONSE||$url_hint| ", $response);

        if (is_null($response)) { 
            throw new CustomException('API request to payhub returned with errors: ', $ex ? $ex->getMessage() : 'unknown error');
        }

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

Payhub::$DATETIME_ZONE = new \DateTimeZone('America/Los_Angeles');

///////////////////////////////////////////////////////////////////////////////////////////////////


