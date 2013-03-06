<?php
namespace Plugins\Core\Storage;

use \FluxAPI\Query;
use \FluxAPI\Field;
use \Doctrine\DBAL\Query\QueryBuilder;
use \Doctrine\DBAL\Schema\Schema;
use \Doctrine\DBAL\Schema\Comparator;

class MySql extends \FluxAPI\Storage
{
    public function addFilters()
    {
        parent::addFilters();

        $this->addFilter('join','filterJoin');
    }

    public function filterSelect(QueryBuilder &$qb, array $params)
    {
        $qb->select($params);
        return $qb;
    }

    public function filterEqual(QueryBuilder &$qb, array $params)
    {
        $type = (isset($params[2]))?$params[2]:'string';

        $qb->andWhere($qb->expr()->eq($params[0],($type!='string')?$params[1]:$qb->expr()->literal($params[1])));
        return $qb;
    }

    public function filterNotEqual(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->neq($params[0],$params[1]));
        return $qb;
    }

    public function filterGreaterThen(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->gt($params[0],$params[1]));
        return $qb;
    }

    public function filterGreaterThenOrEqual(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->gte($params[0],$params[1]));
        return $qb;
    }

    public function filterLessThen(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->lt($params[0],$params[1]));
        return $qb;
    }

    public function filterLessThenOrEqual(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->lte($params[0],$params[1]));
        return $qb;
    }

    public function filterRange(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->andX(
            $qb->expr()->gte($params[0],$params[1]),
            $qb->expr()->lte($params[0],$params[2])
        ));
        return $qb;
    }

    public function filterOrder(QueryBuilder &$qb, array $params)
    {
        $qb->orderBy($params[0],isset($params[1])?$params[1]:'ASC');
        return $qb;
    }

    public function filterLimit(QueryBuilder &$qb, array $params)
    {
        $qb->setFirstResult(intval($params[0]));
        $qb->setMaxResults(intval($params[1]));
        return $qb;
    }

    public function filterCount(QueryBuilder &$qb, array $params)
    {
        $qb->select('COUNT('.$params[0].')');
        return $qb;
    }

    public function filterLike(QueryBuilder &$qb, array $params)
    {
        $qb->andWhere($qb->expr()->like($params[0],$params[1]));
        return $qb;
    }

    public function filterIn(QueryBuilder &$qb, array $params)
    {
        $values = $params[1];

        $in = '';

        if (!is_array($values)) {
            $values = explode(',',$values);
        }

        foreach($values as $i => $value) {
            $in .= $qb->expr()->literal($value);

            if ($i < count($values) -1) {
                $in .= ', ';
            }
        }

        $qb->andWhere($params[0].' IN ('.$in.')');
        return $qb;
    }

    public function filterJoin(QueryBuilder &$qb, array $params)
    {
        $_params = $params;
        array_shift($_params);

        switch($params[0]) {
            case 'inner':
                return $this->filterInnerJoin($qb,$_params);
                break;

            case 'left':
                return $this->filterLeftJoin($qb,$_params);
                break;

            default:
                return $qb;
        }
    }

    public function filterInnerJoin(QueryBuilder &$qb, array $params)
    {
        return $qb;
    }

    public function filterLeftJoin(QueryBuilder &$qb, array $params)
    {
        $qb->leftJoin($params[0],$params[1], $params[1], $params[2]);
        return $qb;
    }

    public function isConnected()
    {
        return isset($this->_api->app['db']);
    }

    public function connect()
    {
        $this->_api->app->register(new \Silex\Provider\DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_mysql',
                'host' => $this->config['host'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
                'dbname' => $this->config['database'],
                'debug_sql' => FALSE,
            ),
        ));
    }

    public function getConnection()
    {
        return $this->_api->app['db'];
    }

    public function loadRelation(\FluxAPI\Model $model, $name)
    {
        if (!$model->hasField($name)) {
            return NULL;
        } else {
            $field = $model->getField($name);
        }

        if ($field->type == Field::TYPE_RELATION && !empty($field->relationModel)) {
            $id = $model->id;
            $model_name = $model->getModelName();
            $id_field_name = strtolower($model_name).'_id';
            $model_class = $model->getClassName();

            $rel_id_field = $this->getRelationField($field);
            $table_name = $this->getTableNameFromModelClass($model_class);
            $relation_table = $this->getRelationTableNameFromModelClass($model_class);

            $rel_field_name = $field->name.'_id';

            $query = new Query();
            $query->filter('join',array('left', $table_name, $relation_table, $relation_table.'.'.$id_field_name.'='.$id))
                  ->filter('equal',array($table_name.'.id',$relation_table.'.'.$rel_field_name,'field'));

            $models = $this->load($model_name,$query);

            if (in_array($field->relationType,array(Field::BELONGS_TO_ONE,Field::HAS_ONE))) {
                if (count($models) > 0) {
                    return $models[0];
                } else {
                    return NULL;
                }
            } else {
                return $models;
            }
        }
        return NULL;
    }

    public function getTableName($name)
    {
        return $this->config['table_prefix'].strtolower($name);
    }

    public function getTableNameFromModelClass($model)
    {
        return $this->getTableName($this->getCollectionName($model));
    }

    public function getRelationTableNameFromModelClass($model)
    {
        return $this->getRelationTableName($this->getCollectionName($model));
    }

    public function getRelationTableName($name)
    {
        return $this->config['table_prefix'].strtolower($name).'_rel';
    }

    public function executeQuery($query)
    {
        parent::executeQuery($query);

        $model = $query->getModel();
        $modelClass = $this->_api->getPluginClass('Model',$model);
        $tableName = $this->getTableNameFromModelClass($modelClass);

        $connection = $this->getConnection();
        $qb = $connection->createQueryBuilder();

        if ($query->getType() == Query::TYPE_INSERT) { // Doctrines query builder does not support INSERTs so we need to create the SQL manually
            $data = $query->getData();

            // remove empty fields
            foreach($data as $name => $value) {
                if (empty($value)) {
                    unset($data[$name]);
                }
            }

            $sql = 'INSERT INTO '.$tableName
                .' ('
                    .implode(', ',array_keys($data))
                .') VALUES(';

                $values = array_values($data);
                foreach($values as $i => $value) {
                    $sql .= $qb->expr()->literal($value);

                    if ($i < count($values) - 1) {
                        $sql .= ',';
                    }
                }
                $sql .= ')';

            if ($this->config['debug_sql']) {
                print("\nSQL: ".$sql."\n");
            }

            $connection->query($sql);
            return TRUE;
        } else {

            if ($query->getType() == Query::TYPE_COUNT) {
                $query->filter('count',array('id'));
            }

            switch($query->getType()) {
                case Query::TYPE_SELECT:
                    $qb->select('*'); // by default select all fields
                    $qb->from($tableName,$tableName);
                    break;

                case Query::TYPE_DELETE:
                    $qb->delete($tableName);
                    break;

                case Query::TYPE_UPDATE:
                    $qb->update($tableName);

                    foreach($query->getData() as $name => $value)
                    {
                        if ($name != 'id') { // do not set the ID again
                            $qb->set($name,$qb->expr()->literal($value));
                        }
                    }
                    break;

                case Query::TYPE_COUNT:
                    $qb->from($tableName,$tableName);
                    break;
            }

            // apply query filters
            $queryFilters = $query->getFilters();

            foreach($queryFilters as $filter) {
                if ($this->hasFilter($filter[0])) {
                    $callback = $this->getFilter($filter[0]);
                    $this->executeFilter($callback,array(&$qb,$filter[1]));
                }
            }

            if ($this->config['debug_sql']) {
                print("\nSQL: ".$qb->getSQL()."\n");
            }

            $result = $qb->execute();

            if (!is_object($result)) {
                return (intval($result) == 1)?FALSE:TRUE;
            }
            $result = $result->fetchAll();

            if ($query->getType() == Query::TYPE_COUNT) {
                return intval($result[0]['COUNT(id)']);
            } else {
                $instances = array();

                foreach($result as $data) {
                    $instances[] = new $modelClass($data);
                }
                return $instances;
            }
        }

        return NULL;
    }

    public function getFieldType(\FluxAPI\Field $field)
    {
        switch($field->type) {
            case Field::TYPE_LONGSTRING:
                $type = 'text';
                break;

            default:
                $type = $field->type;
        }

        return $type;
    }

    public function getFieldConfig(\FluxAPI\Field $field)
    {
        $config = array();

        if (!empty($field->length)) {
            $config['length'] = $field->length;
        }

        if ($field->unsigned) {
            $config['unsigned'] = $field->unsigned;
        }

        if ($field->autoIncrement) {
            $config['autoincrement'] = $field->autoIncrement;
        }

        return $config;
    }

    public function getRelationField(\FluxAPI\Field $field)
    {
        $rel_model_instance = $this->_api->createModel($field->relationModel);

        if (!empty($rel_model_instance)) {
            // we need the id field of the model so we can create a field in the relation table matching the field config
            $rel_id_field = $rel_model_instance->getField('id');

            return $rel_id_field;
        } else {
            return NULL;
        }
    }

    public function migrate($model = NULL)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $connection = $this->getConnection();

        $sm = $connection->getSchemaManager();

        $toSchema = new Schema();

        $models = $this->_api->getPlugins('Model');

        foreach($models as $model => $modelClass) {
            $model = new $modelClass();
            $table_name = $this->getTableNameFromModelClass($modelClass);
            $table = $toSchema->createTable($table_name);
            $primary = array();
            $unique = array();

            // create relation table for this model
            $relation_table = $toSchema->createTable($this->getRelationTableNameFromModelClass($modelClass));
            $relation_primary = array();

            // TODO: split this into multiple methods

            foreach($model->getFields() as $field) {
                if (!empty($field->name) && !empty($field->type)) {

                    if ($field->type != Field::TYPE_RELATION) {

                        $type = $this->getFieldType($field);
                        $config = $this->getFieldConfig($field);

                        $table->addColumn($field->name,$type,$config);

                        // add own model id field to relation table
                        if ($field->name == 'id') {
                            $relation_field_name = $this->getCollectionName($modelClass).'_id';

                            // autoincrement must be removed
                            if (isset($config['autoincrement'])) {
                                unset($config['autoincrement']);
                            }

                            $relation_table->addColumn($relation_field_name, $type, $config);
                            $relation_primary[] = $relation_field_name;
                        }

                        if ($field->primary) {
                            $primary[] = $field->name;
                        }
                    } elseif($field->type == Field::TYPE_RELATION && !empty($field->relationModel)) { // add relation model id field to relation table
                        // we need the id field of the related model so we can create a matching field in the relation table
                        $rel_id_field = $this->getRelationField($field);

                        if ($rel_id_field) {
                            $relation_field_name = $field->name.'_id';

                            $rel_field_type = $this->getFieldType($rel_id_field);
                            $rel_field_config = $this->getFieldConfig($rel_id_field);

                            // autoincrement must be removed
                            if (isset($rel_field_config['autoincrement'])) {
                                unset($rel_field_config['autoincrement']);
                            }

                            $relation_table->addColumn($relation_field_name, $rel_field_type, $rel_field_config);
                            //$relation_primary[] = $relation_field_name;
                        }
                    }
                }

                if (count($primary) > 0) {
                    $table->setPrimaryKey($primary);
                }

                if (count($unique) > 0) {
                    $table->addUniqueIndex($unique);
                }

                // add primary keys to relation table
                if (count($relation_primary)) {
                    $relation_table->setPrimaryKey($relation_primary);
                }
            }
        }

        $sql = array();

        $comparator = new Comparator();
        $sm = $connection->getSchemaManager();
        $dp = $connection->getDatabasePlatform();
        $fromSchema = $sm->createSchema();

        $schemaDiff = $comparator->compare($fromSchema,$toSchema);
        $sql = $schemaDiff->toSql($dp);

        foreach($sql as $query) {
            if ($this->config['debug_sql']) {
                print("\nSQL: ".$query."\n");
            }
            $connection->query($query);
        }
    }
}
