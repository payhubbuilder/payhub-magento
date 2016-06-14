<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////

@include_once Mage::getBaseDir() . '/app/code/local/Payhub/Payment/lib/PHPLib.php';

/////////////////////////////////////////////////////////////////////////////////////////////////////

class Payhub_Payment_Helper_Data extends Mage_Core_Helper_Abstract 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    const LIB_NAMESPACE = 'Payhub';

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static $console;
    private static $filename = "payhub.log";
    private static $classPrefix = 'Payhub_Payment';

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public $log_class_name;
    
    ///////////////////////////////////////////////////////////////////////////////////////////////////

    private $_config;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct() {
        if (class_exists(self::LIB_NAMESPACE . '\\' . 'Log')) { 
            $class_name = $this->log_class_name = self::LIB_NAMESPACE . '\\' . 'Log';

            $logging = $this->getConfigClass()->logging;
            if ($logging == 0) { 
                $log_level = $class_name::LEVEL_NONE;
            } else if ($logging == 1) { 
                $log_level = $class_name::LEVEL_ERROR;
            } else {
                $log_level = $class_name::LEVEL_ALL;
            }

            $class_name::init(array(
                'append' => true,
                'limit' => 1,
                'level' => $log_level,
                'path' => $this->getLogPath(),
            ));
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getBaseDir() {
        return Mage::getBaseDir() . '/app/code/local/Payhub/Payment/';
    }

    public function getLogDir() {
        return Mage::getBaseDir() . '/var/log/';
    }

    public function getLogPath() {
        return $this->getLogDir() . self::$filename;
    }

    public function getLibDir() { 
        return $this->getBaseDir() . 'lib/';
    }

    public function getLibPath($file_name) { 
        return $this->getLibDir() . $file_name;
    }

    public function loadLib($lib_name) { 
        $file_name = $lib_name . '.php';

        require_once $this->getLibPath($file_name);
    }

    public function getLib($lib_name, $ctor_args = null) { 
        $this->loadLib($lib_name);

        $class_name = self::LIB_NAMESPACE . '\\' . $lib_name;

        $instance = new $class_name($ctor_args);

        return $instance;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getConfig($store = null) {
        if (!$this->_config) { 
            $config = array();

            if (is_null($store)) {
                $store = Mage::app()->getWebsite()->getDefaultStore();
            }

            $config['active'] = Mage::getStoreConfig('payment/payhub/active', $store);
            $config['logging'] = Mage::getStoreConfig('payment/payhub/logging', $store);

            $config['api'] = array(
                'url' =>  Mage::getStoreConfig('payment/payhub/api_url', $store),
                'oauth_token' =>  Mage::getStoreConfig('payment/payhub/api_oauth_token', $store),
                'orgid' =>  Mage::getStoreConfig('payment/payhub/account_orgid', $store),
                'tid' =>  Mage::getStoreConfig('payment/payhub/account_tid', $store),
                'mode' =>  Mage::getStoreConfig('payment/payhub/account_test', $store) ? 'demo' : 'live',
            );
            if ($config['api']['mode'] == 'demo') { 
                $config['api']['url'] = 'https://sandbox-api.payhub.com/';
            }

            $this->_config = $config;
        }

        return $this->_config;
    }

    public function getConfigClass($store = null) {
        return (object) $this->getConfig($store);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getStore($storeId) {
        if (!$storeId) {
            throw new Exception('No store id for ' . $storeId);
        }
        $store = Mage::getModel('core/store')->load($storeId);
        if (!$store) {
            throw new Exception('No valid store for ' . $storeId);
        }
        return $store;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function info() {
        if ($this->_logging()) {
            $msg = 'info: ';
            for ($i = 0; $i < func_num_args(); $i++) {
                $msg .= print_r(func_get_arg($i), true);
            }
            $this->_write($msg);
        }
    }

    public function warning() {
        if ($this->_logging()) {
            $msg = 'warning: ';
            for ($i = 0; $i < func_num_args(); $i++) {
                $msg .= print_r(func_get_arg($i), true);
            }
            $this->_write($msg);
        }
    }

    public function error() {
        if ($this->_logging()) {
            $msg = 'error: ';
            for ($i = 0; $i < func_num_args(); $i++) {
                $msg .= print_r(func_get_arg($i), true);
            }
            $this->_write($msg);
        }
        return false;
    }

    public function debug() {
        if ($this->_logging(true)) {
            $msg = 'debug: ';
            for ($i = 0; $i < func_num_args(); $i++) {
                $msg .= print_r($this->_prepareLogEntry(func_get_arg($i)), true);
            }
            $this->_write($msg);
        }
    }

    public function ex() {
        if ($this->_logging()) {
            $ex = func_get_arg(0);
            $context = func_get_arg(1);
            $msg = "an exception has occured during $context: " . $ex->getMessage();
            if (func_num_args() > 2) {
                $msg .= ' (';
                for ($i = 2; $i < func_num_args(); $i++) {
                    $msg .= print_r(func_get_arg($i), true);
                }
                $msg .= ')';
            }
            $this->_write($msg);
        }
        return false;
    }

    private function _prepareLogEntry($value) {
        if (Payhub\ARR::isNumericArray($value) && $value && is_object($value[0]) && (stripos(get_class($value[0]), 'Mage_') == 0 || stripos(get_class($value[0]), self::$classPrefix) == 0)) {
            $arr = array();
            foreach ($value as $_value) {
                $arr[] = $this->_prepareLogEntry($_value);
            }
            return $arr;
        } else if (is_object($value) && stripos(get_class($value), 'Mage_') === 0) {
            if (method_exists($value, 'getId')) {
                return get_class($value) . ' [id = ' . $value->getId() . ']';
            } else {
                return get_class($value);
            }
        } else if (is_object($value) && stripos(get_class($value), self::$classPrefix) === 0) {
            $arr = array('__CLASS__' => get_class($value));
            foreach ($value as $name => $_value) {
                $arr[$name] = $this->_prepareLogEntry($_value);
            }
            return $arr;
        } else {
            return $value;
        }
    }

    public function isVerboseLogging() {
        $config = $this->getConfig();

        return $config['logging'] == 2;
    }

    private function _logging($verbose = false) {
        $config = $this->getConfig();
        if (isset($config['logging'])) {
            if ($config['logging'] == 1 && !$verbose) {
                return true;
            }
            if ($config['logging'] == 2) {
                return true;
            }
        }
        return false;
    }

    private function _write($msg) {
        $uri = $this->getLogPath();
        if (!self::$console) {
            if (!file_exists($this->getLogDir())) {
                mkdir($this->getLogDir(), 0777, true);
                chmod($this->getLogDir(), 0777);
            }
            self::$console = @fopen($uri, "a");
        }

        if (self::$console) {
            $stats = fstat(self::$console);
            if ($stats) {
                $size = $stats['size'] / (1024 * 1000);
                if ($size > 50) {
                    @fclose(self::$console);
                    self::$console = @fopen($uri, "w");
                }
            }
        }

        if (self::$console) {
            $msg = strftime("%Y-%m-%d %H:%M:%S ") . " " . $msg;

            @fwrite(self::$console, print_r($msg, true) . "\n");
            @fflush(self::$console);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

}

