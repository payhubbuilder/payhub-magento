<?php

namespace Payhub;

/////////////////////////////////////////////////////////////////////////////////////////////////////

if (file_exists(dirname(__FILE__) . '/magento.php')) {
    require_once dirname(__FILE__). '/magento.php';
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function is_assoc($value) { 
    return ARR::isAssociativeArray($value);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function require_value($argument_value, $argument_name = null) { 
    if (is_null($argument_value)) { 
        throw new CustomException('Missing value', is_null($argument_name) ? '' : ': ' . $argument_name);
    } else if (is_numeric($argument_value)) { 
        return;
    } else if (is_array($argument_value) || is_object($argument_value)) { 
        return;
    } else if ($argument_value == false) { 
        throw new CustomException('Missing value', is_null($argument_name) ? '' : ': ' . $argument_name);
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function get_or_null($data, $name, $n1 = null, $n2 = null, $n3 = null, $n4 = null, $n5 = null) { 
    if (is_array($data)) { 
        if (isset($data[$name]) == false) { 
            return null;
        }

        $data = $data[$name];
    } else if (is_object($data)) { 
        if (!isset($data->$name)) { 
            return null;
        }
        $data = $data->$name;
    } else {
        return null;
    }

    if (is_null($n1)) { 
        return $data;
    }

    return get_or_null($data, $n1, $n2, $n3, $n4, $n5);
}

function get_or_default() { 
    $args = func_get_args();
    if (count($args) < 3) { 
        throw new CustomException('get_or_default requires at least three parameters');
    }

    $data = array_shift($args);
    $name = array_shift($args);
    $default = array_pop($args);

    if (is_array($name)) { 
        $args = $name;
        $name = array_shift($args);
    }

    if (is_array($data)) { 
        if (isset($data[$name]) == false) { 
            return $default;
        }

        $data = $data[$name];
    } else if (is_object($data)) { 
        if (!isset($data->$name)) { 
            return $default;
        }
        $data = $data->$name;
    } else {
        return $default;
    }

    if (count($args) <= 0) { 
        return $data;
    }

    return get_or_default($data, $args, $default);
}

function get_and_require() {
    $args = func_get_args();
    if (count($args) < 2) { 
        throw new CustomException('get_and_require requires at least two parameters');
    }

    $data = array_shift($args);
    $name = array_shift($args);

    if (is_array($name)) { 
        $args = $name;
        $name = array_shift($args);
    }

    if (is_array($data)) { 
        if (isset($data[$name]) == false) { 
            throw new CustomException('No ', $name);
        }

        $data = $data[$name];
    } else if (is_object($data)) { 
        if (!isset($data->$name)) { 
            throw new CustomException('No ', $name);
        }
        $data = $data->$name;
    } else if ($name) { 
        throw new CustomException('No ', $name);
    } else { 
        throw new CustomException('Missing name');
    }

    if (!$args) { 
        return $data;
    }

    return get_and_require($data, $args);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class CustomException extends \Exception 
{

    public $wrapped;

    public function __construct() {
        if (func_num_args() == 0) {

        } else if (func_num_args() == 1) {
            $ex = func_get_arg(0);
            if ($ex instanceof \Exception) {
                $this->wrapped = $ex;
            } else {
                $this->message = $ex;
            }
        } else {
            $ex = func_get_arg(0);
            if ($ex instanceof \Exception) {
                $msg = $ex->getMessage();
                if ($msg) {
                    $msg .= ' ';
                }
                for ($i = 1; $i < func_num_args(); $i++) {
                    $msg .= print_r(func_get_arg($i), true);
                }
                $ex->message = $msg;
                $this->wrapped = $ex;
            } else {
                $msg = '';
                for ($i = 0; $i < func_num_args(); $i++) {
                    $msg .= print_r(func_get_arg($i), true);
                }
                $this->message = $msg;
            }
        }
    }

    public static function throwEx() {
        if (func_num_args() == 0) {
            throw new \InvalidArgumentException('Missing exception as first parameter');
        } else if (func_num_args() == 1) {
            $ex = func_get_arg(0);
            throw new $ex();
        } else {
            $ex = func_get_arg(0);
            $msg = '';
            for ($i = 1; $i < func_num_args(); $i++) {
                $msg .= print_r(func_get_arg($i), true);
            }
            throw new $ex($msg);
        }
    }

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class APP 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static $_app_mode;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function init($config) {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $configs = array(
            'log' => 'Log',
            'dt'=> 'DT',
        );
        foreach ($configs as $name => $class_name) {
            $config = get_or_null($config, $name);
            if ($config) { 
                $class_name = __NAMESPACE__ . '\\' . $class_name; 

                $class_name::init($config);
            }
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class ARR {

    public static function isAssociativeArray($var) {
        if (!is_array($var)) {
            return false;
        }
        if (!$var) {
            return true;
        }
        return self::isNumericArray($var) == false;
    }

    public static function isNumericArray($var) {
        return is_array($var) && ( ($count = count($var)) == false || (array_key_exists(0, $var) && array_key_exists($count-1, $var)) );
    }
}

class PATH {

    public static function combine() {
        $path = '';
        $ds = '';
        for ($i = 0; $i < func_num_args(); $i++) {
            $arg = func_get_arg($i);
            $arg = rtrim($arg, '/\\');
            if (stripos($arg, '/') === 0 || stripos($arg, '\\') === 0) {
                if ($i > 0) {
                    $arg = substr($arg, 1);
                }
            }
            $path .= $ds;
            $path .= $arg;
            $ds = '/'; //DIRECTORY_SEPARATOR;
        }
        return $path;
    }

}

class STRING 
{
    public static function toCamelCase($str, $uppercase_first_character = false, $options = null) 
    {
        if (!$str) {
            return $str;
        }
        if (stripos($str, ' ') !== false) {
            throw new CustomException('Function with whitespace in string not supported');
        }

        $separator = get_or_default($options, 'separator', '');

        $str = str_ireplace('_', ' ', $str);
        $str = str_ireplace('-', ' ', $str);
        $str = ucwords($str);
        if ($separator != ' ') { 
            $str = str_ireplace(' ', $separator, $str);
        }
        if (!$uppercase_first_character) {
            $str = lcfirst($str);
        }
        return $str;
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class DT {

    const UTC = 'utc';

    private static $_config;
    private static $_now = 'now';
    private static $_today = 'today';

    public static function init($config) {
        self::$_config = $config;
        if ($_now = get_or_null(self::$_config, 'now')) {
            self::$_now = $_now;
        }
        if ($_today = get_or_null(self::$_config, 'today')) {
            self::$_today = $_today;
        }
        if ($_timezone = get_or_null(self::$_config, 'timezone')) {
            date_default_timezone_set($_timezone);
        }
    }

    public static function getNow($date_time_zone = null) {
        if (is_string($date_time_zone)) {
            $date_time_zone = new \DateTimeZone($date_time_zone);
        }
        $dt = new \DateTime(self::$_now, $date_time_zone);
        return $dt;
    }


    public static function getToday($date_time_zone = null) {
        if (is_string($date_time_zone)) {
            $date_time_zone = new \DateTimeZone($date_time_zone);
        }
        $dt = new \DateTime(self::$_today, $date_time_zone);
        return $dt;
    }

    public static function getTodayString($date_time_zone = null) {
        return self::toShortString(self::getToday($date_time_zone));
    }

    public static function getNowString($date_time_zone = null) {
        return self::toString(self::getNow($date_time_zone));
    }

    public static function toShortString($date_time) {
        if (is_string($date_time)) {
            throw new CustomException('toShortString must be used with DateTime');
        }
        return $date_time->format('Y-m-d');
    }

    public static function toString($date_time) {
        if (is_string($date_time)) {
            return $date_time;
        }
        return $date_time->format('c');
    }


}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class Log 
{
    const LEVEL_NONE = 0;
    const LEVEL_ERROR = 1; //includes exceptions
    const LEVEL_WARNING = 2;
    const LEVEL_INFO = 3;
    const LEVEL_DEBUG = 4;
    const LEVEL_NETWORK = 5;
    const LEVEL_DB = 6;
    const LEVEL_DATA = 7;
    const LEVEL_ALL = 99;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static $_handle;
    private static $_config; // level, base, append (default true), limit (in MB)
    private static $_level = Log::LEVEL_ERROR;
    private static $_path;

    private static $_ex_callback;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function init($config) {
        self::$_config = $config;
        self::$_path = self::_getURI();
        self::$_level = get_or_null($config, 'level');
        if (!get_or_null(self::$_config, 'append')) {
            self::clear();
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function getLogLevel() {
        return self::$_level;
    }

    public static function clear() {
        //self::_write('', true);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function setExCallback($callback) { 
        self::$_ex_callback = $callback;
    }

    public static function clearExCallback() { 
        self::$_ex_callback = null;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function info() {
        if (self::$_level >= Log::LEVEL_INFO) {
            self::_prepareAndWrite('info', func_get_args());
        }
    }

    public static function warning() {
        if (self::$_level >= Log::LEVEL_WARNING) {
            self::_prepareAndWrite('warning', func_get_args());
        }
    }

    public static function error() {
        if (self::$_level >= Log::LEVEL_ERROR) {
            self::_prepareAndWrite('error', func_get_args());
        }
    }

    public static function debug() {
        if (self::$_level >= Log::LEVEL_DEBUG) {
            self::_prepareAndWrite('debug', func_get_args());
        }
    }

    public static function data() {
        if (self::$_level >= Log::LEVEL_DATA) {
            self::_prepareAndWrite('data', func_get_args());
        }
    }

    public static function network() {
        if (self::$_level >= Log::LEVEL_NETWORK) {
            self::_prepareAndWrite('network', func_get_args());
        }
    }

    public static function db() {
        if (self::$_level >= Log::LEVEL_DB) {
            self::_prepareAndWrite('db', func_get_args());
        }
    }

    public static function ex() {
        if (self::$_level >= Log::LEVEL_ERROR) {
            $msg = 'exception ';
            if (func_num_args() > 1) {
                $msg .= 'An exception has occured during ' . func_get_arg(1);
                $msg .= ': ';
            }
            if (func_num_args() > 0) {
                $ex = func_get_arg(0);

                $msg .= $ex->getMessage();
            }
            for ($i = 2; $i < func_num_args(); $i++) {
                $msg .= print_r(func_get_arg($i), true);
            }
            self::_write($msg);
            if (func_num_args() > 0) {
                self::_write(func_get_arg(0)->getTraceAsString());
            }

            if (self::$_ex_callback && $ex) { 
                $callback = self::$_ex_callback;

                $callback($ex);
            }
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static function _prepareAndWrite($type, $args) {
        $msg = $type . ' ';
        for ($i = 0; $i < count($args); $i++) {
            $msg .= print_r($args[$i], true);
        }
        self::_write($msg);
    }

    public static function getPath() {
        return self::$_path;
    }

    private static function _getURI() {
        $path = get_or_null(self::$_config, 'path');
        if ($path) { 
            return $path;
        }
        $name = get_or_null(self::$_config, 'name', 'log.txt');
        $dir = get_or_null(self::$_config, 'dir', __DIR__);

        return PATH::combine($dir, $name);
    }

    private static function _write($msg, $clear = false) {
        $uri = self::$_path;
        if (!self::$_handle) {
            self::$_handle = @fopen($uri, get_or_null(self::$_config, 'append') ? 'a' : 'w');
        }

        if (self::$_handle) {
            $stats = fstat(self::$_handle);
            if ($stats) {
                $size = $stats['size'] / (1024 * 1000);
                if ($size > get_or_default(self::$_config, 'limit', 10) || $clear) {
                    @fclose(self::$_handle);
                    self::$_handle = @fopen($uri, "w");
                }
            }
        }

        if (self::$_handle && !$clear) {
            $msg = DT::getNowString() . ' ' . $msg;

            @fwrite(self::$_handle, print_r($msg, true) . PHP_EOL);
            @fflush(self::$_handle);
        }
    }

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class CURLException extends CustomException
{
    public $status_code;
    public $response;

    public function __construct($status_code, $response) { 
        $this->status_code = $status_code;
        $this->response = $response ? $response : 'no-response';
        $this->message = "$status_code {$this->response}";

        $remaining_arguments = array_slice(func_get_args(), 2);

        array_unshift($remaining_arguments, $this);

        call_user_func_array('parent::__construct', $remaining_arguments);
    }

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class CURL 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static $last_http_code;
    public static $last_response;
    public static $last_content_type;
    public static $last_response_header;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static $_config;
    private static $_retries;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function init($config) {
        self::$_config = $config;

        self::$_retries = get_or_default($config, 'retries', false);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static function _init_request($url, $options, $additional_options) {
        $options = array_merge_recursive($options, is_null($additional_options) ? array() : $additional_options);

        $query_arguments = get_or_null($options, 'query_arguments');
        $headers = get_or_null($options, 'headers');
        $curlopt = get_or_null($options, 'curlopt');
        $use_fiddler = get_or_null($options, 'use_fiddler');

        $ch = curl_init();

        $query_url = $use_fiddler ? 'http://localhost:8888' : $url;

        if ($query_arguments) {
            $query_url .= STRING::endsWith($url, '?') ? '' : '?' . http_build_query($query_arguments);
        }

        curl_setopt($ch, CURLOPT_URL, $query_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, self::_prepareHeaders($headers));
        }

        if ($curlopt) { 
            foreach($curlopt as $name => $value) { 
                curl_setopt($ch, $name, $value);
            }
        }

        return $ch;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function init_get_request($url, $data, $options, $additional_options = null) { 
        if ($data) { 
            $options['query_arguments'] = $data;
        }
        $ch = self::_init_request($url, $options, $additional_options);

        Log::network('|CURL::init_get_request| url=', $url, ' data=', $data);

        return $ch;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function init_post_request($url, $data, $options, $additional_options = null) { 
        $ch = self::_init_request($url, $options, $additional_options);

        Log::network('|CURL::init_post_request| url=', $url, ' data=', $data);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        if ($data) {
            if (is_array($data) || is_object($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else if (is_string($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                throw new CURLException(null, null, 'Invalid parameter type for request: ', $data);
            }
        }
        return $ch;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    private static function _do_request($ch, $options) {
        $retry = 0;

        $response = null;

        $exponential_backoff = get_or_default(self::$_config, 'exponential_backoff', false);

        do {
            $response = curl_exec($ch);

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            Log::network('|CURL::do_request| retry=', $retry, ' http_code=', $http_code, ' response=', $response);

            if (self::_do_retry($http_code, $retry)) {
                ++$retry;

                if ($exponential_backoff) { 
                    sleep(exp(2, $retry));
                } else { 
                    sleep($retry);
                }
            } else {
                break;
            }
        } while (true);

        $response = self::_end_request($ch, $http_code, $response, $content_type, $options);

        return $response;
    }

    private static function _do_retry($http_code, $retry) {
        if (is_null(self::$_retries) && $retry >= 3) { 
            return false;
        } else if (self::$_retries == false) { 
            return false;
        } else if ($retry >= self::$_retries) { 
            return false;
        }

        if ($http_code && substr($http_code, 0, 2) == '20') {
            return false;
        }
        if ($http_code && substr($http_code, 0, 2) == '40') {
            return false;
        }
        return true;
    }

    private static function _end_request($ch, $http_code, $response, $content_type, $options) {
        
		self::$last_http_code = $http_code;
        self::$last_response = $response;
        self::$last_content_type = $content_type;
        self::$last_response_header = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		curl_close($ch);

        if (!$http_code || substr($http_code, 0, 2) != '20') {
            // json message might be json encoded again (creating an invalid json)?
            if (stripos($content_type, 'json') !== false) {
                $response = preg_replace('/[{}\[\]"\']/', '', $response);
            }
            throw new CURLException($http_code, $response);
        }

        if (get_or_null($options, 'as_array')) { 
            if (stripos($content_type , 'json') !== false) { 
                $response = json_decode($response, true); 
            } else if (stripos($content_type , 'xml') !== false) { 
                $response = XML::toArray($response); 
            } else { 
                throw new CustomException('Response type not implemented');
            }
        }

        return $response;
    }

    private static function _prepareHeaders($headers) {
        if (is_assoc($headers)) {
            $_headers = array();
            foreach($headers as $name => $value) {
                $_headers[] = "$name: $value";
            }
            $headers = $_headers;
        }
        return $headers;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function get($url, $data = null, $options = null) {
        require_value($url, 'url');

        $ch = self::init_get_request($url, $data, $options);

        $response = self::_do_request($ch, $options);

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function post($url, $data = null, $options = null) {
        require_value($url, 'url');

        $ch = self::init_post_request($url, $data, $options);

        $response = self::_do_request($ch, $options);

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public static function post_json($url, $json, $options = null) {
        require_value($url, 'url');
        require_value($json, 'json');

        if ($json && is_string($json) == false) {
            $json = json_encode($json);
        }

        $ch = self::init_post_request($url, $json, $options, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
        ));

        $response = self::_do_request($ch, $options);

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

}

///////////////////////////////////////////////////////////////////////////////////////////////////


