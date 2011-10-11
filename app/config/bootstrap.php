<?php

function debug() {
    $request = \app()->request();
    $vars = func_get_args();
    $called = debug_backtrace();

    if ($request->isCli()) {
        foreach ($vars as $var) {
            print_r($var);
        }
    } else {
        echo '<div class="wl-debug">';
        echo '<p><b>' . trim($called[0]['file']) . '</b> (' . $called[0]['line'] . ')</b></p>';
        echo '<pre>';

        foreach ($vars as $var) {
            print_r($var);
        }

        echo '</pre>';
        echo '</div>';
    }
}