<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////

class Payhub_Payment_Model_Method_Payhub extends Mage_Payment_Model_Method_Cc 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    const METHOD_CODE = 'payhub';

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    protected $_code  = self::METHOD_CODE;

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    protected $_formBlockType = 'payment/form_cc';
    protected $_infoBlockType = 'payment/info_cc';

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    protected $_isGateway               = true;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_canFetchTransactionInfo = false;

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    protected $_allowCurrencyCode = array('USD');

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->getAcceptedCurrencyCodes())) {
            return false;
        }
        return true;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function getAcceptedCurrencyCodes() {
        if (!$this->hasData('_accepted_currency')) {
            $acceptedCurrencyCodes = $this->_allowCurrencyCode;
            $acceptedCurrencyCodes[] = $this->getConfigData('currency');
            $this->setData('_accepted_currency', $acceptedCurrencyCodes);
        }
        return $this->_getData('_accepted_currency');
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function canRefund() {
        return $this->_canRefund;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function canVoid(Varien_Object $payment) {
        return $this->_canVoid;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function cancel(Varien_Object $payment) {
        $this->refund($payment);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function void(Varien_Object $payment) { 
        $this->refund($payment);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function refund(Varien_Object $payment, $amount) { 
        $transaction_id = $payment->getParentTransactionId();

        if (floatval($payment->getOrder()->getGrandTotal()) > floatval($amount)) { 
            Mage::throwException(Mage::helper('payment')->__('An online refund is only possible for the entire amount of the capture transaction. Please refund the invoice offline in Magento and refund the amount manually in your Payhub website account!'));
        }

        $exceptions = null;
        $response = null;

        if (is_null($response)) { 
            try { 
                ///##transaction already refunded##
                ///response_code: 4073
                ///response_text: 'Unable to void previous transaction.'

                $payhub = Mage::getModel('payhub/payhub');
                $response = $payhub->void($transaction_id);
            } catch(Exception $ex) { 
                $exceptions[] = $ex;

                $response = null;
            }
        }

        if (is_null($response)) { 
            try { 
                ///##transaction not refundable (not settled, already refunded)##
                ///response_code: 4074
                ///response_text: 'Unable to refund previous transaction.'

                $payhub = Mage::getModel('payhub/payhub');
                $response = $payhub->refund($transaction_id);
            } catch(Exception $ex) { 
                $exceptions[] = $ex;

                $response = null;
            }
        }

        if (is_null($response) == false) { 
            $payment->setParentTransactionId($transaction_id);

            $this->_addTransaction('Refund', $payment, $response);
        } else { 
            $message = 'The Payhub credit card transaction was neither voidable nor refundable. Please issue a manual refund through the Payhub web portal! Further details can be found in the Payhub log file.';

            Mage::helper('payhub')->error($message);

            foreach($exceptions as $ex) { 
                if ($ex) { 
                    Mage::helper('payhub')->ex($ex, 'refund');
                }
            }

            Mage::throwException(Mage::helper('payment')->__($message));
        }

        return $this;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function capture(Varien_Object $payment, $amount) { 
        parent::capture($payment, $amount);

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for Payhub transaction.'));
        }

        $cc_number = $payment->getCcNumber();

        $test = $cc_number == false && Mage::helper('payhub')->getConfigClass()->api['mode'] === 'demo';

        if ($test) { 
            $data = array(
                'cc' => '4012881888818888',
                'cvv' => '999',
                'amount' => '10.00',
                'month' => rand()%12+1,
                'year' => 2020+rand()%20,
            );
        } else { 
            $data = array(
                'cc' => $payment->getCcNumber(),
                'cvv' => $payment->getCcCid(),
                'amount' => $amount,
                'month' => $payment->getCcExpMonth(),
                'year' => $payment->getCcExpYear(),
            );
        }

        $data = array_merge($data, $this->_getCustomerDetails($payment->getOrder()));

        $response = null;
        try { 
            $payhub = Mage::getModel('payhub/payhub');

            $response = $payhub->sale($data);
        } catch(Exception $ex) { 
            Mage::helper('payhub')->ex($ex, 'capture');

            Mage::throwException(Mage::helper('payment')->__($ex ? $ex->getMessage() : 'An unknown error occurred during the Payhub credit card transaction!'));
        }

        $payment->setAmount($amount);

        $this->_addTransaction('Capture', $payment, $response);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    private function _addTransaction($context, Mage_Sales_Model_Order_Payment $payment, $response) { 
        $payment->setTransactionId($response['transaction_id']);
        $payment->setSkipTransactionCreation(false);

        unset($response['transaction_id']);

        $message = "Payhub $context Transaction |";

        $payment->resetTransactionAdditionalInfo();
        foreach($response as $key => $value) { 
            $key = Payhub\STRING::toCamelCase($key, true, array(
                'separator' => ' ',
            ));

            $payment->setTransactionAdditionalInfo($key, strval($value));

            $message .= " $key=$value";
        }

        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false, $message);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    private function _getCustomerDetails($entity) { 
        Mage::helper('payhub')->loadLib('magento');

        $order = new Payhub\ORDER();
        $order->from_entity($entity);

        $customer = $order->customer;

        $data = array(
            'first_name' => Payhub\get_or_null($customer, 'first_name'),
            'last_name' => Payhub\get_or_null($customer, 'last_name'),
            'phone' => Payhub\get_or_default($order,  'shipping_address', 'phone', Payhub\get_or_null($customer, 'billing_address', 'phone')),
            'email' => Payhub\get_or_null($customer, 'email'),
            'address1' => Payhub\get_or_null($order, 'billing_address', 'street1'),
            'address2' => Payhub\get_or_null($order, 'billing_address', 'street2'),
            'city' => Payhub\get_or_null($order, 'billing_address', 'city'),
            'state' => Payhub\get_or_null($order, 'billing_address', 'state'),
            'zip' => Payhub\get_or_null($order, 'billing_address', 'postcode'),
            'ship_to_name' => Payhub\get_or_null($order, 'shipping_address', 'name'),
            'ship_address1' => Payhub\get_or_null($order, 'shipping_address', 'street1'),
            'ship_address2' => Payhub\get_or_null($order, 'shipping_address', 'street2'),
            'ship_city' => Payhub\get_or_null($order, 'shipping_address', 'city'),
            'ship_state' => Payhub\get_or_null($order, 'shipping_address', 'state'),
            'ship_zip' => Payhub\get_or_null($order, 'shipping_address', 'postcode'),
        );
        if (strlen($data['phone']) !== 10) { 
            unset($data['phone']);
        }

        return $data;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    //public function validate() { 
        //DEBUG
    //}

    ///////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

