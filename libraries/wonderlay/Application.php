<?php

namespace wonderlay;

/**
 * This is the main application object.
 */
class Application {

    /**
     * Framework's version.
     *
     * @var string
     */
    private $_version = '0.0.1';

    /**
     * Configuration values.
     *
     * @var array
     */
    protected $_config = array();


    /**
     * Class instances.
     *
     * @var array
     */
    protected $_instances = array();

    /**
     * Callback methods.
     *
     * @var array
     */
    protected $_callbacks = array();

    /**
     * Class constructor.
     *
     * @param array $config Array containing config keys and values.
     * @return void
     */
    public function __construct($config = array()) {
        $this->config($config);
    }

    /**
     * Return or set configuration values.
     *
     * @param mixed $value An array if you wanna set configs or a string if you
     *                     wanna get a config value.
     * @param mixed $default A default value to be used if nothing can be found.
     */
    public function config($value = null, $default = null) {
        // Setter
        if (is_array($value) && count($value) > 0) {
            $this->_config = $this->arrayMergeRecursiveReplace($this->_config, $value);
        // Getter
        } else {
            if (empty($value)) {
                return $this->_config;
            }

            if (isset($this->_config[$value])) {
                return $this->_config[$value];
            }

            if (strpos($value, '.') !== false) {
                $config = $this->_config;
                $parts = explode('.', $value);

                foreach ($parts as $part) {
                    if (isset($config[$part])) {
                        $config = $config[$part];
                    } else {
                        $config = $default;
                    }
                }

                return $config;
            }

            return $default;
        }
    }

    public function factory($className, $params = array()) {
        $instanceHash = md5($className . var_export($params, true));

        // Return already instantiated object instance if set
        if (isset($this->_instances[$instanceHash])) {
            return $this->_instances[$instanceHash];
        }

        $paramCount = count($params);

        switch ($paramCount) {
        case 0:
            $instance = new $className();
            break;
        case 1:
            $instance = new $className(current($params));
            break;
        case 2:
            $instance = new $className($params[0], $params[1]);
            break;
        case 3:
            $instance = new $className($params[0], $params[1], $params[2]);
            break;
        default:
            $class = new \ReflectionClass($className);
            $instance = $class->newInstanceArgs($params);
            break;
        }

        return $this->setInstance($instanceHash, $instance);
    }

    public function setInstance($hash, $instance) {
        $this->_instances[$hash] = $instance;

        return $instance;
    }

    public function router() {
        return $this->factory(__NAMESPACE__ . '\Router');
    }

    public function request() {
        return $this->factory(__NAMESPACE__ . '\Request');
    }

    public function loader() {
        $class = __NAMESPACE__ . '\ClassLoader';

        if (!class_exists($class, false)) {
            require __DIR__ . '/ClassLoader.php';
        }

        return $this->factory($class);
    }

    public function module($module, $init = true) {
        if (strpos($module, '\\') === false) {
            $module = preg_replace('/[^a-zA-Z0-9_]/', '', $module);
            $module = str_replace(' ', '\\', ucwords(str_replace('_', ' ', $module)));
            $class = 'app\modules\\' . $module . '\Controller';
        } else {
            $class = $module;
        }

        if (!class_exists($class)) {
            return false;
        }

        $object = new $class($this);

        if ($init === true) {
            if (method_exists($object, 'init')) {
                $object->init();
            }
        }

        return $object;
    }

    public function dispatch($module, $action = 'index', $params = array()) {
        if ($module instanceof \wonderlay\ModuleController) {
            $object = $module;
        } else {
            $object = $this->module($module);

            if ($object === false) {
                throw new exception\FileNotFound('Module \'' . $module . '\' not found.');
            }
        }

        if (!is_callable(array($object, $action))) {
            throw new exception\FileNotFound('Module \'' . $module . '\' does not have a callable method \'' . $action . '\'');
        }

        return call_user_func_array(array($object, $action), (array) $params);
    }

    public function dispatchRequest($module, $action, $params = array()) {
        $method = $this->request()->method;

        if (strtolower($method) === strtolower($action)) {
            $action = $action . (strpos($action, 'Method') === false ? 'Method' : '');
        } else {
            $action = $action . (strpos($action, 'Action') === false ? 'Action' : '');
        }

        return $this->dispatch($module, $action, $params);
    }

    public function addMethod($method, $callback) {
        $this->_callbacks[$method] = $callbacks;
    }

    public function __call($method, $args) {
        if (isset($this->_callbacks[$method]) && is_callable($this->_callbacks[$method])) {
            $callback = $this->_callbacks[$method];

            return call_user_func_array($callback, $args);
        } else {
            throw new \BadMethodCallException('Method \'' . __CLASS__ . '::' . $method . '\' not found or the command is not a valid callback type.');
        }
    }

    public function arrayMergeRecursiveReplace() {
        // Holds all the arrays passed
        $params =  func_get_args();

        // First array is used as the base, everything else overwrites on it
        $return = array_shift($params);

        // Merge all arrays on the first array
        foreach ($params as $array) {
            foreach ($array as $key => $value) {
                // Numeric keyed values are added (unless already there)
                if (is_numeric($key) && (!in_array($value, $return))) {
                    if (is_array($value)) {
                        $return[] = $this->arrayMergeRecursiveReplace($return[$key], $value);
                    } else {
                        $return[] = $value;
                    }
                // String keyed values are replaced
                } else {
                    if (isset($return[$key]) && is_array($value) && is_array($return[$key])) {
                        $return[$key] = $this->arrayMergeRecursiveReplace($return[$key], $value);
                    } else {
                        $return[$key] = $value;
                    }
                }
            }
        }

        return $return;
    }
}