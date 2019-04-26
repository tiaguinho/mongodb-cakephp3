<?php

namespace Hayko\Mongodb\Database\Schema;

use Cake\Database\Schema\TableSchema;
use Hayko\Mongodb\Database\Driver\Mongodb;
use MongoDB\BSON\ObjectId;

class MongoSchema
{

    /**
     * Database Connection
     *
     * @var resource
     * @access protected
     */
    protected $_connection = null;

    /**
     * Constructor
     *
     * @param Mongodb $conn connection
     * @access public
     */
    public function __construct(Mongodb $conn)
    {
        $this->_connection = $conn;
    }

    /**
     * Describe
     *
     * @access public
     * @param string $name describe
     * @return TableSchema
     */
    public function describe($name)
    {
        if (strpos($name, '.')) {
            list(, $name) = explode('.', $name);
        }

        $table = new TableSchema($name);

        if (empty($table->primaryKey())) {
            $table->addColumn('_id', ['type' => 'string', 'default' => new ObjectId(), 'null' => false]);
            $table->addConstraint('_id', ['type' => 'primary', 'columns' => ['_id']]);
        }

        return $table;
    }
}
