<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////

class Payhub_Payment_Model_Adminhtml_Cctypes
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static $_ALLOWED = array(
        'VI',
        'MC',
        'AE',
        'DI',
        'OT',
    );
    
    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function toOptionArray($isMultiselect = false) {
        $options = array();

        foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
            if (in_array($code, self::$_ALLOWED)) { 
                $options[] = array(
                    'value' => $code,
                    'label' => $name
                );
            }
        }

        return $options;
    }

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

