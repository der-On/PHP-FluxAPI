<?php
namespace FluxAPI;

use \Doctrine\DBAL\Query\QueryBuilder;

abstract class Storage
{
    protected $_api = NULL;
    protected $_filters = array();
    public $config = array();

    public function __construct(Api $api, array $config = array())
    {
        $this->config = array_replace_recursive($this->config,$config);
        $this->_api = $api;

        $this->addFilters();
    }

    public static function getCollectionName($model)
    {
        $parts = explode('\\',$model);
        return strtolower($parts[count($parts)-1]);
    }

    public function addFilters()
    {

    }

    public function addFilter($name,$callback)
    {
        if (!$this->hasFilter($name)) {
            $this->_filters[$name] = $callback;
        }
        return $this; // make it chainable
    }

    public function hasFilter($name)
    {
        return (isset($this->_filters[$name]) && !empty($this->_filters[$name]));
    }

    public function getFilter($name)
    {
        if ($this->hasFilter($name)) {
            return $this->_filters[$name];
        } else {
            return NULL;
        }
    }

    public function getFilters()
    {
        return $this->_filters;
    }

    public function count($model, Query $query = NULL)
    {
        if (empty($query)) {
            $query = new Query();
        }

        $query->setType(Query::TYPE_COUNT);

        $result = $this->executeQuery($query);
        return $result;
    }

    public function exists($model, Model $instance)
    {
        if (isset($instance->id) && !empty($instance->id)) {
            $query = new Query();
            $query->setType(Query::TYPE_COUNT);
            $query->setModel($model);
            $query->filter('equals',array('id',$instance->id));

            $result = $this->executeQuery($query);
            return $result > 0;
        }

        return FALSE;
    }

    public function save($model, Model $instance)
    {
        $query = new Query();
        $query->setType(Query::TYPE_INSERT);

        if ($this->exists($model, $instance)) {
            $query->setType(Query::TYPE_UPDATE);
        }

        $query->setModel($model);
        $query->setData($instance->toArray());
        return $this->executeQuery($query);
    }

    public function load($model, Query $query = NULL)
    {
        if (empty($query)) {
            $query = new Query();
        }
        $query->setType(Query::TYPE_SELECT);
        $query->setModel($model);
        return $this->executeQuery($query);
    }

    public function update($model, Query $query = NULL, array $data = array())
    {
        if (empty($query)) {
            $query = new Query();
        }
        $query->setType(Query::TYPE_UPDATE);
        $query->setModel($model);
        $query->setData($data);
        return $this->executeQuery($query);
    }

    public function delete($model, Query $query = NULL)
    {
        if (empty($query)) {
            $query = new Query();
        }
        $query->setType(Query::TYPE_DELETE);
        $query->setModel($model);

        return $this->executeQuery($query);
    }

    public function executeQuery(Query $query)
    {
        $query->setStorage($this);

        if (!$this->isConnected()) {
            $this->connect();
        }

        return NULL;
    }

    public function isConnected()
    {
        return FALSE;
    }

    public function connect()
    {

    }

    public function getConnection()
    {
        return NULL;
    }

    public function migrate($model = NULL)
    {

    }
}
