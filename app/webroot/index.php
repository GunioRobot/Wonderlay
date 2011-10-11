<?php

////////////////////////////////////////////////////////////////////////////////

ini_set('error_reporting', -1);
ini_set('display_errors', 1);

define('DS', DIRECTORY_SEPARATOR);
define('LIBRARIES_PATH', dirname(dirname(__DIR__)) . DS . 'libraries');
define('WONDERLAY_PATH', LIBRARIES_PATH . DS . 'wonderlay');
define('APPBASE_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', APPBASE_PATH . DS . 'app');
define('WEBROOT_PATH', APP_PATH . DS . 'webroot');

////////////////////////////////////////////////////////////////////////////////

require WONDERLAY_PATH . DS . 'Application.php';

$config = require APP_PATH . DS . 'config' . DS . 'configs.php';

$app = new \wonderlay\Application($config);

unset($config);

if (!function_exists('app')) {
    function app() {
        global $app; return $app;
    }
}

////////////////////////////////////////////////////////////////////////////////

require APP_PATH . DS . 'config' . DS . 'bootstrap.php';

$loader = $app->loader();

$loader->registerNamespaces(array(
    'wonderlay' => LIBRARIES_PATH,
    'app' => APPBASE_PATH
));

$loader->register();

////////////////////////////////////////////////////////////////////////////////

$router = $app->router();

require APP_PATH . DS . 'config' . DS . 'routes.php';

$request = $app->request();
$params = $router->match($request->method, $request->path);

if (is_array($params)) {
    $request->setParams($params);
}

$content = '';

if (!empty($params['module']) && !empty($params['action'])) {
    $content = $app->dispatchRequest($params['module'], $params['action']);
} elseif(is_string($params)) {
    $content = $params;
}

debug($content);

////////////////////////////////////////////////////////////////////////////////