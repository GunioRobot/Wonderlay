<?php

namespace wonderlay;

/**
 * This is the request object that will organize data comming from superglobals
 * like $_GET, $_POST, $_SERVER and so on.
 *
 * @todo Implement detectors creating a new method like `Request::is('mobile')`
 *       with the ability to change or extend the way these detectors work.
 * @todo Parse and organize the Accep-* headers so we can use it to return the
 *       correct requested document.
 */
class Request {

    /**
     * GET data.
     *
     * @var array
     */
    public $query = array();

    /**
     * POST and FILES data.
     *
     * @var array
     */
    public $body = array();

    /**
     * Request scheme `http|https|cli`.
     *
     * @var string
     */
    protected $scheme = 'http';

    /**
     * Request host.
     *
     * @var string
     */
    protected $host = 'localhost';

    /**
     * Request port.
     *
     * @var int
     */
    protected $port = 80;

    /**
     * Request method.
     *
     * @var string
     */
    protected $method = 'GET';

    /**
     * Path to the application's webroot.
     *
     * @var string
     */
    protected $webroot = '/';

    /**
     * Clean request path to the application.
     *
     * @var string
     */
    protected $path = '/';

    /**
     * Full request uri.
     *
     * @var strng
     */
    protected $self = '/';

    /**
     * Params for request.
     *
     * @var array
     */
    protected $_params = array();

    /**
     * Request referer.
     *
     * @var string
     */
    protected $referrer;

    /**
     * Request headers.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Client's IP address.
     *
     * @var string
     */
    protected $ip;

    /**
     * Flag to define if this is a common http request.
     *
     * @var bool
     */
    protected $_isHttp = false;

    /**
     * Flag to define if this is a command line request.
     *
     * @var bool
     */
    protected $_isCli = false;

    /**
     * Pulls request data from superglobals and tries to normalize it. This will
     * also work for CLI requests.
     *
     * @return void
     */
    public function __construct() {
        // Handling magic quotes...
        if (get_magic_quotes_gpc()) {
            $stripslashesGpc = function(&$value, $key) {
                $value = stripslashes($value);
            };

            array_walk_recursive($_GET, $stripslashesGpc);
            array_walk_recursive($_POST, $stripslashesGpc);
            array_walk_recursive($_COOKIE, $stripslashesGpc);
            array_walk_recursive($_REQUEST, $stripslashesGpc);
        }

        // We are dealing with a http request
        if (isset($_SERVER['HTTP_HOST'])) {
            $this->_isHttp = true;

            // Defining the scheme to https if this is a secure connection
            if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
                $this->scheme .= 's';
            }

            $this->host = $_SERVER['HTTP_HOST'];
            $this->port = $_SERVER['SERVER_PORT'];
            $this->method = $_SERVER['REQUEST_METHOD'];

            // Emulating REST for browsers
            if ($this->method === 'POST' && !empty($_POST['_method'])) {
                $this->method = strtoupper($_POST['_method']);
            }

            $this->webroot = $this->_getWebroot();

            if (!empty($_GET['url'])) {
                $this->path = '/' . trim($_GET['url'], '/');
                unset($_GET['url']);
            }

            $this->self = $_SERVER['REQUEST_URI'];

            if (!empty($_SERVER['HTTP_REFERER'])) {
                $this->referrer = $_SERVER['HTTP_REFERER'];
            }

            $files = isset($_FILES) ? $_FILES : array();

            $this->body = array_merge($_POST, $files);
            $this->query = $_GET;
            $this->headers = $this->_formatHeaders();
            $this->ip = $this->_getIp();
        } else {
            // Here we are dealing with a CLI request
            $this->_isCli = true;
            $this->scheme = 'cli';

            $args = getopt('u:');

            if (!empty($args['u'])) {
                $this->self = $args['u'];

                $query = parse_url($args['u'], PHP_URL_QUERY);

                // If there are query parameters, let's take care of those...
                if ($query) {
                    $this->path = str_replace('?' . $query, '', $args['u']);
                    parse_str($query, $this->query);
                } else {
                    $this->path = $args['u'];
                }
            }
        }
    }

    /**
     * Here we expose protected attributes of the object. If the attribute is
     * not present, we look for it on the `$_params` array.
     *
     * @param string $attr The object attribute/param key to return.
     * @return mixed Returns the object attribute or value of `$params[$attr]`
     *               or `null` if nothing is found.
     */
    public function __get($attr) {
        if (strpos($attr, '_') !== 0 && isset($this->{$attr})) {
            return $this->{$attr};
        } elseif (!empty($this->_params[$attr])) {
            return $this->_params[$attr];
        }

        return null;
    }

    /**
     * Set parameters to the request object
     *
     * @param mixed $key This can be a string defining the parameter key or an
     *                   associative array containing both keys and values.
     * @param mixed $value The value associated with the given key.
     * @return void
     */
    public function setParams($key, $value = null) {
        if (is_array($key)) {
            $this->_params = array_merge($this->_params, $key);
        } else {
            $this->_params[$key] = $value;
        }
    }

    /**
     * Return param value
     *
     * @param string $key This can be a string definig the parameter to get or
     *                    if it is `null` we just return all the parameters.
     * @param mixed $default If we can't find the specified key, we return this
     *                       value.
     * @return mixed
     */
    public function param($key = null, $default = null) {
        if (is_null($key)) {
            return $this->_params;
        }

        if (!empty($this->_params[$key])) {
            return $this->_params[$key];
        }

        return $default;
    }

    /**
     * Checks if the request method is GET
     *
     * @return bool
     */
    public function isGet() {
        return $this->method === 'GET';
    }

    /**
     * Checks if the request method is POST
     *
     * @return bool
     */
    public function isPost() {
        return $this->method === 'POST';
    }

    /**
     * Checks if the request method is PUT
     *
     * @return bool
     */
    public function isPut() {
        return $this->method === 'PUT';
    }

    /**
     * Checks if the request method is DELETE
     *
     * @return bool
     */
    public function isDelete() {
        return $this->method === 'DELETE';
    }

    /**
     * Checks if this is a secure connection
     *
     * @return bool
     */
    public function isSecure() {
        return $this->scheme === 'https';
    }

    /**
     * Checks if the request is being made from the CLI
     *
     * @return bool
     */
    public function isCli() {
        return $this->_isCli;
    }

    /**
     * Checks if the request is being made from a Flash application
     *
     * @return bool
     */
    public function isFlash() {
        return ($this->header['User-Agent'] === 'Shockwave Flash');
    }

    /**
     * Checks if this is an ajax request
     *
     * @return bool
     */
    public function isAjax() {
        if (!empty($this->headers['X-Requested-With'])
        && strtolower($this->headers['X-Requested-With']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Tries to resolve the app's webroot path relative to the host
     *
     * @return string
     */
    protected function _getWebroot() {
        $ru = explode('/', $_SERVER['REQUEST_URI']);
        $sn = explode('/', $_SERVER['SCRIPT_NAME']);
        $intersection = array_intersect($ru, $sn);

        if ($intersection) {
            return '/' . trim(str_replace('/index.php', '', implode('/', $intersection)), '/');
        }

        return '/';
    }

    /**
     * Tries to normalize request headers
     *
     * @return array
     */
    protected function _formatHeaders() {
        $headers = array();

        // If apache_request_headers is available, just use those
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            // Otherwise, we loop the $_SERVER variable to find out headers
            // and try to format them the right way
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $key = strtolower(str_replace('HTTP_', '', $key));
                    $key = str_replace('_', ' ', $key);
                    $key = ucwords($key);
                    $key = str_replace(' ', '-', $key);

                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Gets the client's IP address
     *
     * @link http://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php
     * @return string
     */
    protected function _getIp() {
        // Helper function to validate IP addresses
        $validate = function($ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }

            return true;
        };

        // Check for shared internet/ISP IP
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && $validate($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        // Check for IPs passing through proxies
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check if multiple IPs exist in var
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            foreach ($iplist as $ip) {
                if ($validate($ip)) {
                    return $ip;
                }
            }
        }

        $check = array(
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        );

        foreach ($check as $c) {
            if (!empty($_SERVER[$c]) && $validate($_SERVER[$c])) {
                return $_SERVER[$c];
            }
        }

        // Return unreliable ip since all else failed
        return $_SERVER['REMOTE_ADDR'];
    }
}