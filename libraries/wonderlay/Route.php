<?php

namespace wonderlay;

class Route {

    protected $_name;

    protected $_route;

    protected $_isStatic = false;

    protected $_regexp;

    protected $_defaultParams = array();

    protected $_namedParams = array();

    protected $_optionalParams = array();

    protected $_methodParams = array(
        'GET' => array(),
        'POST' => array(),
        'PUT' => array(),
        'DELETE' => array()
    );

    protected $_routeParamRegex = '\<[\:|\*|\#]([^\>]+)\>';

    protected $_routeOptionalParamRegex = '\(([^\<]*)\<[\:|\*|\#]([^\>]+)\>([^\)]*)\)';

    protected $_paramRegex = '([a-zA-Z0-9\_\-\+\%\s]+)';

    protected $_paramRegexNumeric = '([0-9]+)';

    protected $_paramRegexWildcard = '(.*)';

    protected $_condition;

    protected $_callback;

    public function __construct($route) {
        $routeRegex = null;
        $routeParams = array();
        $routeOptionalParams = array();
        $route = trim($route, '/');
        $routeRegex = $route;

        if (strpos($route, '<') === false) {
            $this->isStatic(true);
        }

        if (!$this->isStatic()) {
            $regexOptionalMatches = array();

            preg_match_all('@' . $this->_routeOptionalParamRegex . '@', $route, $regexOptionalMatches, PREG_SET_ORDER);

            if (isset($regexOptionalMatches[0]) && count($regexOptionalMatches) > 0) {
                foreach ($regexOptionalMatches as $paramMatch) {
                    $routeOptionalParams[$paramMatch[2]] = array(
                        'routeSegment' => $paramMatch[0],
                        'prefix' => $paramMatch[1],
                        'suffix' => $paramMatch[3],
                    );

                    $routeParamToken = substr($paramMatch[0], strlen('(' . $paramMatch[1]));
                    $routeParamToken = substr($routeParamToken, 0, -strlen($paramMatch[3] . ')'));
                    $routeRegex = str_replace($paramMatch[0], '(?:' . preg_quote($paramMatch[1]) . $routeParamToken . preg_quote($paramMatch[3]) . ')?', $routeRegex);
                }
            }

            $regexMatches = array();
            preg_match_all('@' . $this->_routeParamRegex . '@', $route, $regexMatches, PREG_PATTERN_ORDER);

            if (isset($regexMatches[1]) && count($regexMatches[1]) > 0) {
                $routeParamsMatched = array();

                foreach ($regexMatches[1] as $paramIndex => $paramName) {
                    if (strpos($paramName, '|') !== false) {
                        $paramParts = explode('|', $paramName);
                        $routeParamsMatched[] = $paramParts[0];
                        $routeRegex = str_replace('<:' . $paramName . '>', '(' . $paramParts[1] . ')', $routeRegex);
                    } else {
                        $routeParamsMatched[] = $paramName;
                        $routeRegex = str_replace('<:' . $paramName . '>', $this->_paramRegex, $routeRegex);
                        $routeRegex = str_replace('<#' . $paramName . '>', $this->_paramRegexNumeric, $routeRegex);
                        $routeRegex = str_replace('<*' . $paramName . '>', $this->_paramRegexWildcard, $routeRegex);
                    }
                }

                $routeParams = array_combine($routeParamsMatched, $regexMatches[0]);
            } else {
                $this->_isStatic = true;
            }

            $routeRegex = str_replace('/', '\/', $routeRegex);
        }

        $this->_route = $route;
        $this->_regexp = '/^' . $routeRegex . '$/';
        $this->_namedParams = $routeParams;
        $this->_optionalParams = $routeOptionalParams;
    }

    public function defaults($params = array()) {
        if (count($params) > 0) {
            $this->_defaultParams = $params;

            return $this;
        }

        return $this->_defaultParams;
    }

    public function namedParams() {
        return $this->_namedParams;
    }

    public function optionalParams() {
        return $this->_optionalParams;
    }

    public function optionalParamDefaults() {
        $defaultParams = $this->defaults();
        $optionalParamDefaults = array();

        if ($this->_optionalParams && count($this->_optionalParams) > 0) {
            foreach ($this->_optionalParams as $paramName => $opts) {
                if (isset($defaultParams[$paramName])) {
                    $optionalParamDefaults[$paramName] = $defaultParams[$paramName];
                } else {
                    $optionalParamDefaults[$paramName] = null;
                }
            }
        }

        return $optionalParamDefaults;
    }

    public function isStatic($static = null) {
        if ($static !== null) {
            $this->_isStatic = $static;
        }

        return $this->_isStatic;
    }

    public function name($name = null) {
        if ($name === null) {
            return $this->_name;
        }

        $this->_name = $name;

        return $this;
    }

    public function route() {
        return $this->_route;
    }

    public function regexp() {
        return $this->_regexp;
    }

    public function get($params) {
        return $this->methodDefaults('GET', $params);
    }

    public function post($params) {
        return $this->methodDefaults('POST', $params);
    }

    public function put($params) {
        return $this->methodDefaults('PUT', $params);
    }

    public function delete($params) {
        return $this->methodDefaults('DELETE', $params);
    }

    public function methodDefaults($method, array $params = array()) {
        $method = strtoupper($method);

        if (!isset($this->_methodParams[$method])) {
            $this->_methodParams[$method] = $params;
        } else {
            $this->_methodParams[$method] += $params;
        }

        if (count($params) === 0) {
            return $this->_methodParams[$method];
        }

        return $this;
    }

    public function condition($callback = null) {
        // Setter
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('Condition provided is not a valid callback. Given (' . gettype($callback) . ')');
            }

            $this->_condition = $callback;

            return $this;
        }

        return $this->_condition;
    }

    public function callback($callback = null) {
        // Setter
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('The after match callback provided is not valid. Given (' . gettype($callback) . ')');
            }

            $this->_callback = $callback;

            return $this;
        }

        return $this->_callback;
    }
}