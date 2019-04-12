<?php

namespace Hayko\Mongodb\ORM;

use Cake\ORM\Entity;
use Exception;
use MongoDB\BSON\Serializable;

class Document
{

    /**
     * store the document
     *
     * @var array $_document
     * @access protected
     */
    protected $_document;

    /**
     * table model name
     *
     * @var string $_registryAlias
     * @access protected
     */
    protected $_registryAlias;

    /**
     * set document and table name
     *
     * @param array|Traversable $document
     * @param string $table
     * @access public
     */
    public function __construct($document, $table)
    {
        $this->_document = $document;
        $this->_registryAlias = $table;
    }

    /**
     * convert mongo document into cake entity
     *
     * @return \Cake\ORM\Entity
     * @access public
     * @throws Exception
     */
    public function cakefy()
    {
        $document = [];
        foreach ($this->_document as $field => $value) {
            $type = gettype($value);
            if ($type == 'object') {
                switch (get_class($value)) {
                    case 'MongoDB\BSON\ObjectId':
                        $document[$field] = $value->__toString();
                        break;

                    case 'MongoDB\BSON\UTCDateTime':
                        $document[$field] = $value->toDateTime();
                        break;

                    case 'MongoDB\Model\BSONDocument':
                    default:
                        if ($value instanceof Serializable) {
                            $document[$field] = $value->bsonSerialize();
                        } else {
                            throw new Exception(get_class($value) . ' conversion not implemented.');
                        }
                }
            } elseif ($type == 'array') {
                $document[$field] = $this->cakefy();
            } else {
                $document[$field] = $value;
            }
        }

        return new Entity($document, ['markClean' => true, 'markNew' => false, 'source' => $this->_registryAlias]);
    }
}
