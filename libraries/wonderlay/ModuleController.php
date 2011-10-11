<?php

namespace wonderlay;

class ModuleController {

    protected $app;

    public function __construct($app) {
        $this->app = $app;
    }

    public function init() {}
}