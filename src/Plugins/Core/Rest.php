<?php
namespace Plugins\Core;

class Rest
{
    protected $_api = NULL;

    public function __construct(\FluxAPI\Api $api)
    {
        $this->_api = $api;

        $this->registerRoutes();
    }

    public function registerRoutes()
    {
        
    }
}
