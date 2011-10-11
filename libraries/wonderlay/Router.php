<?php

namespace wonderlay;

class Router {

    protected $_routes = array();

    protected $_matchedRouteName;

    public function route($name, $route) {
        $route = new Route($route);

        $this->_routes[$name] = $route->name($name);

        return $route;
    }

    public function routes() {
        return $this->_routes;
    }

    public function match($method, $url) {
        if (count($this->_routes) === 0) {
            throw new \OutOfBoundsException('There must be at least one route defined to match for.');
        }

        // Clean up URL for matching
        $url = trim($url, '/');
        $params = array();

        // Loop over set routes to find a match
        // Order matters - Looping will stop when first suitable match is found
        $routes = $this->routes();

        foreach ($routes as $routeName => $route) {
            if ($params = $this->_routeMatch($route, $method, $url)) {
                // Check condition callback if set
                $condition = $route->condition();

                if ($condition !== null) {
                    // Pass in method, url, and matched params
                    $conditionResponse = call_user_func($condition, $params, $method, $url);

                    // Condition returned false - no match - skip route and clear matched data
                    if ($conditionResponse === false) {
                        $params = array();

                        $this->_matchedRouteName = null;

                        continue;
                    }
                }

                break;
            }
        }

        // Run 'afterMatch' callback if one is provided
        if ($params) {
            // If we have an after match callback, we can use it to modify params
            $callback = $route->callback();

            if ($callback !== null) {
                // Pass in method, url, and matched params
                $params = call_user_func($callback, $params, $method, $url);
            }
        }

        return $params;
    }


    /**
     * Match URL against a specific given route
     */
    protected function _routeMatch($route, $method, $url) {
        $params = array();

        // Static route - no PREG overhead
        if ($route->isStatic()) {
            $routeUrl = $route->route();

            // Match? (already cleaned/trimmed)
            if ($routeUrl === $url) {
                // Return defaults + HTTP method params
                $params = array_merge($route->defaults(), $route->methodDefaults($method));
            }

            // Store matched route name
            $this->_matchedRouteName = $route->name();

        // Match params
        } else {
            $result = preg_match($route->regexp(), $url, $matches);

            if ($result) {
                // Store matched route name
                $this->_matchedRouteName = $route->name();

                // Shift off first 'match' result - full URL input string
                array_shift($matches);

                // Only named params, leaving off optionals
                $namedParams = array_merge($route->namedParams(), $route->optionalParamDefaults());
                $namedParamsNotOptional = array_diff_key($namedParams, $route->optionalParamDefaults());
                $namedParamsMatched = $namedParamsNotOptional;

                // Equalize matched params, rely on matching order
                // @todo Switch all routes to named captures to avoid this. Man, all these regex woes make my head hurt.
                // @link http://www.regular-expressions.info/named.html
                $namedParamsIndexed = array_keys($namedParams);
                $mi = count($namedParamsNotOptional);

                while (count($matches) > $mi) {
                    $namedParamsMatched[$namedParamsIndexed[$mi]] = $namedParams[$namedParamsIndexed[$mi]];
                    $mi++;
                }

                // Combine params
                if (count($namedParamsMatched) !== count($matches)) {
                    // Route has inequal matches to named params
                    throw new \InvalidArgumentException('Error matching URL to route params: matched(' . count($matches) . ') !== named(' . count($namedParamsMatched) . ')');
                }

                $params = array_combine(array_keys($namedParamsMatched), $matches);

                if (strtoupper($method) !== 'GET') {
                    // Default REST behavior is to be 'greedy' and always use the REST method defaults if supplied
                    $params = array_merge($route->namedParams(), $route->defaults(), $params, $route->methodDefaults($method));
                } else {
                    $params = array_merge($route->namedParams(), $route->defaults(), $route->methodDefaults($method), $params);
                }
            }
        }

        return array_map('urldecode', $params);
    }


    /**
     * Return last matched route
     */
    public function matchedRoute() {
        if ($this->_matchedRouteName) {
            return $this->_routes[$this->_matchedRouteName];
        } else {
            throw new \LogicException('Unable to return last route matched - No route has been matched yet.');
        }
    }


    /**
     * Put a URL together by matching route name and params
     *
     * @param array $params Array of key => value params to fill in for given route
     * @param string $routeName Name of the route previously defined
     *
     * @return string Full matched URL as string with given values put in place of named parameters
     * @throws UnexpectedValueException For non-existent route name or params that don't match given route name (Unable to create URL string)
     */
    public function url($params = array(), $routeName = null) {
        // If params is string, assume route name for static route
        if ($routeName === null && is_string($params)) {
            $routeName = $params;
            $params = array();
        }

        if (!$routeName) {
            throw new \UnexpectedValueException('Error creating URL: Route name must be specified.');
        }

        if (!isset($this->_routes[$routeName])) {
            throw new \UnexpectedValueException('Error creating URL: Route name \'' . $routeName . '\' not found in defined routes.');
        }

        $routeUrl = '';
        $route = $this->_routes[$routeName];
        $routeUrl = $route->route();

        // Static routes - let's save some time here
        if ($route->isStatic()) {
            return $routeUrl;
        }

        $routeDefaults = $route->defaults();
        $routeParams = array_merge($routeDefaults, $route->namedParams());
        $optionalParams = $route->optionalParams();

        // Match all params on route that do not have defaults
        $matchedParams = $routeDefaults; // Begin with defaults

        foreach (array_merge($matchedParams, $params) as $key => $value) {
            // Optional params
            if (isset($optionalParams[$key])) {
                // If no given value, or given value is the same as default, set value to empty
                if ((isset($routeDefaults[$key]) && !isset($params[$key]))) {
                    $matchedParams[$key] = '';
                } else {
                    $matchedParams[$key] = $optionalParams[$key]['prefix'] . $value . $optionalParams[$key]['suffix'];
                }

                $routeParams[$key] = $optionalParams[$key]['routeSegment'];
            // Required/standard param
            } elseif (isset($routeParams[$key])) {
                $matchedParams[$key] = $value;
            }
        }

        // Ensure all params have been matched, exception if not
        if (count(array_diff_key($matchedParams, $routeParams)) > 0) {
            throw new \UnexpectedValueException('Error creating URL: Route \'' . $routeName . '\' has parameters that have not been matched.');
        }

        // Fill in values and put URL together
        foreach ($routeParams as $paramName => $paramPlaceholder) {
            if (!isset($matchedParams[$paramName])) {
                throw new \UnexpectedValueException('Error creating URL for route \'' . $routeName . '\': Required route parameter \'' . $paramName . '\' has not been supplied.');
            }

            $routeUrl = str_replace($paramPlaceholder, urlencode($matchedParams[$paramName]), $routeUrl);
        }

        // Remove all optional parameters with no supplied match or default value
        foreach ($optionalParams as $param) {
            $routeUrl = str_replace($param['routeSegment'], '', $routeUrl);
        }

        // Ensure escaping characters are removed
        $routeUrl = str_replace('\\', '', $routeUrl);

        return $routeUrl;
    }


    /**
     * Clear existing routes to start over
     */
    public function reset() {
        $this->_routes = array();
    }
}