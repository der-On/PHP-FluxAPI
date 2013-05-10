<?php

namespace FluxAPI\Factory;


use FluxAPI\Exception\AccessDeniedException;

class ControllerFactory
{
    protected $_api;

    protected $_controllers = array();

    public function __construct(\FluxAPI\Api $api)
    {
        $this->_api = $api;
    }

    public function getControllerClass($controller_name)
    {
        if (!$this->_api['permissions']->hasControllerAccess($controller_name)) {
            throw new AccessDeniedException(sprintf('You are not allowed to use the "%s" Controller.', $controller_name));
            return NULL;
        }

        return $this->_api['plugins']->getPluginClass('Controller', $controller_name);
    }

    public function getController($controller_name)
    {
        if (!$this->_api['permissions']->hasControllerAccess($controller_name)) {
            throw new AccessDeniedException(sprintf('You are not allowed to use the "%s" Controller.', $controller_name));
            return NULL;
        }

        $controller = $this->getControllerClass($controller_name);

        if ($controller) {
            if (!isset($this->_controllers[$controller_name])) {
                $this->_controllers[$controller_name] = new $controller($this->_api);
            }

            return $this->_controllers[$controller_name];
        }

        return NULL;
    }

    /**
     * Calls the action of a controller if both are registered.
     *
     * @param string $controller_name
     * @param string $action
     * @param [array $params]
     * @return mixed
     */
    public function call($controller_name, $action, $params = array())
    {
        // abort if access is denied
        if (!$this->_api['permissions']->hasControllerAccess($controller_name, $action)) {
            throw new AccessDeniedException(sprintf('You are not allowed to call %s->%s.', $controller_name, $action));
            return;
        }

        $controller = $this->getControllerClass($controller_name);

        if ($controller) {
            if ($controller::hasAction($action)) {
                $instance = $this->getController($controller_name);
                return call_user_func_array(array($instance, $action), $params);
            }
        } else {
            throw new \RuntimeException(sprintf('Controller "%s" is not registered.', $controller_name));
            return;
        }
    }

    /**
     * Returns all actions for all controllers or a single controller.
     *
     * @param [string $controller_name]
     * @return array - if $controller_name is given a list of actions, if no $controller_name is given a list of controllers as keys and actions as values.
     */
    public function getActions($controller_name = NULL)
    {
        if (empty($controller_name)) {
            $controllers = $this->_api['plugins']->getPlugins('Controller');

            $actions = array();

            foreach($controllers as $name => $controller) {
                $actions[$name] = $controller::getActions();
            }

            return $actions;
        } else {
            $controller = $this->_api['plugins']->getPluginClass('Controller', $controller_name);

            if ($controller) {
                return $controller::getActions();
            }
        }

        return array();
    }
}