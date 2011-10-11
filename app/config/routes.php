<?php

$router->route('module-item', '/<:module>/<#item>')
    ->defaults(array('action' => 'view', 'format' => 'html'))
    ->put(array('action' => 'put'))
    ->delete(array('action' => 'delete'));

$router->route('module-action-item', '/<:module>/<:action>/<#item>')
    ->defaults(array('format' => 'html'));

$router->route('module-action', '/<:module>/<:action>')
    ->defaults(array('format' => 'html'));

$router->route('module', '/<:module>')
    ->defaults(array('action' => 'index', 'format' => 'html'));

$router->route('home', '/')
    ->defaults(array( 'module' => 'Home', 'action' => 'index', 'format' => 'html'));