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

    public function __construct($config) { 
        $this->url = get_and_require($config, 'url');
        $mode = get_and_require($config, 'mode');
        if ($mode === 'live') { 
            $this->standard_request = array(
                'username' => get_and_require($config, 'username'),
                'password' => get_and_require($config, 'password'),
                'orgid' => get_and_require($config, 'orgid'),
                'tid' => get_and_require($config, 'tid'),
                'mode' => $mode,
            );
        } else if ($mode === 'demo') { 
            $this->standard_request = array(
                'mode' => $mode,
                'username' => 'test-user',
                'password' => 'test-password',
                'orgid' => '99999',
                'tid' => '111222',
            );
        } else { 
            throw new CustomException('Invalid Payhub mode: ', $mode);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function sale($data, $options = null) { 
        $response = $this->_getResponse('sale', $this->_prepare_data($data), $options);

        $response = array(
            'transaction_id' => get_or_null($response, 'TRANSACTION_ID'),
            'transaction_date_time' => $this->toLocaltime(get_or_null($response, 'TRANSACTION_DATE_TIME')),
            'approval_code' => get_or_null($response, 'APPROVAL_CODE'),
            'card_token_no' => get_or_null($response, 'CARD_TOKEN_NO'),
            'batch_id' => get_or_null($response, 'BATCH_ID'),
            'customer_id' => get_or_null($response, 'CUSTOMER_ID'),
        );

        return $response;
    }

    public function void($transaction_id, $options = null) { 
        require_value($transaction_id, 'Transaction ID');

        $response = $this->_getResponse('void', array('trans_id' => $transaction_id), $options);

        $response = array(
            'transaction_id' => get_or_null($response, 'TRANSACTION_ID'),
            'parent_transaction_id' => $transaction_id,
            'transaction_date_time' => $this->toLocaltime(get_and_require($response, 'TRANSACTION_DATE_TIME')),
            'card_token_no' => get_or_null($response, 'CARD_TOKEN_NO'),
            'batch_id' => get_or_null($response, 'BATCH_ID'),
        );

        return $response;
    }

    public function refund($transaction_id, $options = null) { 
        require_value($transaction_id, 'Transaction ID');

        $response = $this->_getResponse('refund', array('trans_id' => $transaction_id), $options);

        $response = array(
            'transaction_id' => get_or_null($response, 'TRANSACTION_ID'),
            'parent_transaction_id' => $transaction_id,
            'transaction_date_time' => $this->toLocaltime(get_and_require($response, 'TRANSACTION_DATE_TIME')),
            'card_token_no' => get_or_null($response, 'CARD_TOKEN_NO'),
            'batch_id' => get_or_null($response, 'BATCH_ID'),
        );

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private function _prepare_data(& $data) { 
        foreach($data as $name => & $value) { 
            $value = trim(strval($value));
        }
        if (isset($data['month'])) { 
            $data['month'] = str_pad($data['month'], 2, '0', STR_PAD_LEFT);
        }
        if (isset($data['year'])) { 
            $len = strlen($data['year']);
            if ($len == 4) { 
                // valid year
            } else if ($len == 2) { 
                $data['year'] = '20' . $data['year'];
            } else { 
                // invalid year, let API throw error
            }
        }

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

    private function _getResponse($action, $request, $options = null) { 
        require_value($action, 'API action');

        $request = array_merge($request, $this->standard_request);

        $request['trans_type'] = $action;

        $response = null;
        $ex = null;

        Log::network("|payhub||REQUEST||$action| ", $request);

        try { 
            if (get_or_null($options, 'post_form')) { 
                $response = CURL::post($this->url, $request, array_merge(array(
                    'as_array' => true,
                    'curlopt' => array(
                        CURLOPT_SSL_VERIFYPEER => 0, 
                    ),
                ), is_null($options) ? array() : $options));
            } else { 
                $response = CURL::post_json($this->url, $request, array_merge(array(
                    'as_array' => true,
                    'curlopt' => array(
                        CURLOPT_SSL_VERIFYPEER => 0, 
                    ),
                ), is_null($options) ? array() : $options));
            }
        } catch(\Exception $ex) { 
            Log::ex($ex, "|payhub||$action|");

            $response = null;
        }

        Log::network("|payhub||RESPONSE||$action| ", $response);

        if (is_null($response)) { 
            throw new CustomException('API request to payhub returned with errors: ', $ex ? $ex->getMessage() : 'unknown error');
        }

        $response_code = get_and_require($response, 'RESPONSE_CODE');
        if ($response_code !== '00') { 
            $response_text = get_or_null($response, 'RESPONSE_TEXT');
            if (!$response_text) { 
                $response_text = get_or_null($response, 'RESPONSE_CODE');
            }
            throw new CustomException('API request to payhub returned with errors: ', $response_text);
        }

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

Payhub::$DATETIME_ZONE = new \DateTimeZone('America/Los_Angeles');

///////////////////////////////////////////////////////////////////////////////////////////////////


