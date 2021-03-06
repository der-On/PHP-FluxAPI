<?php
namespace FluxAPI\Cache;

class ModelSource extends CacheSource
{
    public $model_name = NULL;
    public $query = NULL;
    public $instances = NULL;

    public function __construct($model_name, \FluxAPI\Query $query = NULL, \FluxAPI\Collection\ModelCollection $models = NULL)
    {
        $this->model_name = $model_name;
        $this->query = $query;
        $this->models = $models;
    }

    /**
     * Returns a unique hash string from the source
     *
     * @return string
     */
    public function toHash()
    {
        if ($this->query) {
            return $this->query->toString();
        } else {
            return $this->model_name;
        }
    }
}