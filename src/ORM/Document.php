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

                    default:     
                        if ($value instanceof \MongoDB\BSON\Serializable) {                  
                                $document[$field] = $this->serializeObjects($value);
                           } else {
                            throw new Exception(get_class($value) . ' conversion not implemented.');
                         }
            } elseif ($type == 'array') {
               $document[$field] = $this->cakefy();
            } else {
                $document[$field] = $value;
            }
        }
        
        $inflector = new \Cake\Utility\Inflector();
        $entityName = '\\App\\Model\\Entity\\'.$inflector->singularize($this->_registryAlias);
        return new $entityName($document, ['markClean' => true, 'markNew' => false, 'source' => $this->_registryAlias]);
    }
    
    
    
    
    private function serializeObjects($obj){
       if ($obj instanceof \MongoDB\BSON\Serializable) {      
          foreach($obj as $field=> $value){         
               if ($value instanceof \MongoDB\BSON\Serializable) {
                   $obj[$field] = $this->serializeObjects($value);
               }
           }
           return $obj->bsonSerialize();
       }else{
           return $obj;
       }                     
    }
}
