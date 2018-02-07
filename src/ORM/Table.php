<?php

namespace Hayko\Mongodb\ORM;

use ArrayObject;
use BadMethodCallException;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table as CakeTable;
use RuntimeException;

class Table extends CakeTable
{

    /**
     * return MongoCollection object
     *
     * @return \MongoDB\Collection
     * @access private
     */
    private function __getCollection()
    {
        $driver = $this->getConnection()->getDriver();
        $collection = $driver->getCollection($this->getTable());

        return $collection;
    }

    /**
     * always return true because mongo is schemaless
     *
     * @param string $field
     * @return bool
     * @access public
     */
    public function hasField($field)
    {
        return true;
    }

    /**
     * find documents
     *
     * @param string $type
     * @param array $options
     * @return MongoQuery|\Cake\ORM\Entity
     * @access public
     * @throws \Exception
     */
    public function find($type = 'all', $options = [])
    {
        $query = new MongoFinder($this->__getCollection(), $options);
        $method = 'find' . ucfirst($type);
        if (method_exists($query, $method)) {
            $alias = $this->getAlias();
            $mongoCursor = $query->{$method}();
            if ($mongoCursor instanceof \MongoDB\Model\BSONDocument) {
                return (new Document($mongoCursor, $alias))->cakefy();
            } elseif (is_array($mongoCursor)) {
                return $mongoCursor;
            }
            $results = new ResultSet($mongoCursor, $alias);

            if (isset($options['whitelist'])) {
                return new MongoQuery($results->toArray(), $query->count());
            } else {
                return $results->toArray();
            }
        }

        throw new BadMethodCallException(
            sprintf('Unknown method "%s"', $method)
        );
    }

    /**
     * get the document by _id
     *
     * @param string $primaryKey
     * @param array $options
     * @return \Cake\ORM\Entity
     * @access public
     * @throws \Exception
     */
    public function get($primaryKey, $options = [])
    {
        $query = new MongoFinder($this->__getCollection(), $options);
        $result = $query->get($primaryKey);

        if ($result) {
            $document = new Document($result, $this->getAlias());
            return $document->cakefy();
        }

        throw new InvalidPrimaryKeyException(sprintf(
            'Record not found in table "%s" with primary key [%s]',
            $this->_table->table(),
            $primaryKey
        ));
    }

    /**
     * remove one document
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array $options
     * @return bool
     * @access public
     */
    public function delete(EntityInterface $entity, $options = [])
    {
        try {
            $collection = $this->__getCollection();
            $success = $collection->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($entity->_id)]);
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
            return false;
        }
        return $success;
    }

    /**
     * delete all rows matching $conditions
     * @param $conditions
     * @return int
     * @throws \Exception
     */
    public function deleteAll($conditions)
    {
        try {
            $collection = $this->__getCollection();
            $query = new MongoFinder($collection);
            $rows = $query->find(['projection' => ['_id' => 1]]);
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = $row->_id;
            }
            $delete = $collection->deleteMany(['_id' => ['$in' => $ids]]);
            return $delete->getDeletedCount();
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
            return false;
        }
    }

    /**
     * save the document
     *
     * @param EntityInterface $entity
     * @param array $options
     * @return mixed $success
     * @access public
     */
    public function save(EntityInterface $entity, $options = [])
    {
        $options = new ArrayObject($options + [
            'checkRules' => true,
            'checkExisting' => true,
            '_primary' => true
        ]);

        if ($entity->getErrors()) {
            return false;
        }

        if ($entity->isNew() === false && !$entity->isDirty()) {
            return $entity;
        }

        $success = $this->_processSave($entity, $options);
        if ($success) {
            if ($options['_primary']) {
                $this->dispatchEvent('Model.afterSaveCommit', compact('entity', 'options'));
                $entity->isNew(false);
                $entity->source($this->getRegistryAlias());
            }
        }

        return $success;
    }

    /**
     * insert or update the document
     *
     * @param \Cake\ORM\Entity $entity
     * @param array $options
     * @return mixed $success
     * @access protected
     */
    protected function _processSave($entity, $options)
    {
        $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        $event = $this->dispatchEvent('Model.beforeSave', compact('entity', 'options'));
        if ($event->isStopped()) {
            return $event->result;
        }

        $data = $entity->toArray();
        $isNew = $entity->isNew();

        //convert to mongodate
        if (isset($data['created'])) {
            $data['created']  = new \MongoDB\BSON\UTCDateTime(strtotime($data['created']->toDateTimeString()));
        }
        if (isset($data['modified'])) {
            $data['modified'] = new \MongoDB\BSON\UTCDateTime(strtotime($data['modified']->toDateTimeString()));
        }

        if ($isNew) {
            $success = $this->_insert($entity, $data);
        } else {
            $success = $this->_update($entity, $data);
        }

        if ($success) {
            $this->dispatchEvent('Model.afterSave', compact('entity', 'options'));
            $entity->clean();
            if (!$options['_primary']) {
                $entity->isNew(false);
                $entity->setSource($this->getRegistryAlias());
            }

            $success = true;
        }

        if (!$success && $isNew) {
            $entity->unsetProperty($this->getPrimaryKey());
            $entity->isNew(true);
        }

        if ($success) {
            return $entity;
        }

        return false;
    }

    /**
     * insert new document
     *
     * @param \Cake\ORM\Entity $entity
     * @param array $data
     * @return mixed $success
     * @access protected
     */
    protected function _insert($entity, $data)
    {
        $primary = (array)$this->getPrimaryKey();
        if (empty($primary)) {
            $msg = sprintf(
                'Cannot insert row in "%s" table, it has no primary key.',
                $this->getTable()
            );
            throw new RuntimeException($msg);
        }
        $primary = ['_id' => $this->_newId($primary)];

        $filteredKeys = array_filter($primary, 'strlen');
        $data = $data + $filteredKeys;

        $success = false;
        if (empty($data)) {
            return $success;
        }

        $success = $entity;
        $collection = $this->__getCollection();

        if (is_object($collection)) {
            $result = $collection->insertOne($data);
            if ($result->isAcknowledged()) {
                $entity->set('_id', $result->getInsertedId());
            }
        }
        return $success;
    }

    /**
     * update one document
     *
     * @param \Cake\ORM\Entity $entity
     * @param array $data
     * @return mixed $success
     * @access protected
     */
    protected function _update($entity, $data)
    {
        unset($data['_id']);

        $success = $entity;
        $collection = $this->__getCollection();

        if (is_object($collection)) {
            $r = $collection->update(['_id' => new \MongoDB\BSON\ObjectId($entity->_id)], $data);
            if ($r['ok'] == false) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * create new MongoDB\BSON\ObjectId
     *
     * @param mixed $primary
     * @return \MongoDB\BSON\ObjectId
     * @access public
     */
    protected function _newId($primary)
    {
        if (!$primary || count((array)$primary) > 1) {
            return null;
        }

        return new \MongoDB\BSON\ObjectId();
    }
}
