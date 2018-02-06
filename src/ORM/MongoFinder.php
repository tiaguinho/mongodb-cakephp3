<?php

namespace Hayko\Mongodb\ORM;

class MongoFinder
{

    /**
     * connection with db
     *
     * @var \MongoDB\Client $_connection
     * @access protected
     */
    protected $_connection;

    /**
     * default options for find
     *
     * @var array $_options
     * @access protected
     */
    protected $_options = [
        'fields' => [],
        'where' => [],
    ];

    /**
     * total number of rows
     *
     * @var int $_totalRows
     * @access protected
     */
    protected $_totalRows;

    /**
     * set connection and options to find
     *
     * @param Mongo $connection
     * @param array $options
     * @access public
     */
    public function __construct($connection, $options = [])
    {
        $this->connection($connection);
        $this->_options = array_merge_recursive($this->_options, $options);

        if (isset($options['conditions']) && !empty($options['conditions'])) {
            $this->_options['where'] += $options['conditions'];
            unset($this->_options['conditions']);
        }

//        $this->__normalizeFieldsName($this->_options); // How do I search nested data with it ?
        if (!empty($this->_options['where'])) {
            $this->__translateNestedArray($this->_options['where']);
            $this->__translateConditions($this->_options['where']);
        }
    }

    /**
     * Convert ['foo' => 'bar', ['baz' => true]]
     * to
     * ['$and' => [['foo', 'bar'], ['$and' => ['baz' => true]]]
     * @param $conditions
     */
    private function __translateNestedArray(&$conditions)
    {
        $and = isset($conditions['$and']) ? (array)$conditions['$and'] : [];
        foreach ($conditions as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                unset($conditions[$key]);
                $and[] = $value;
            } elseif (is_array($value) && !in_array(strtoupper($key), ['OR', '$OR', 'AND', '$AND'])) {
                $this->__translateNestedArray($conditions[$key], $key);
            }
        }
        if (!empty($and)) {
            $conditions['$and'] = $and;
            foreach (array_keys($conditions['$and']) as $key) {
                $this->__translateNestedArray($conditions['$and'][$key]);
            }
        }
    }

    /**
     * connection
     *
     * @param Mongo $connection
     * @return \MongoDB\Client
     * @access public
     */
    public function connection($connection = null)
    {
        if ($connection === null) {
            return $this->_connection;
        }

        $this->_connection = $connection;
    }

    /**
     * remove model name from the key
     *
     * example: Categories.name -> name
     * @param array $data
     * @access private
     */
    private function __normalizeFieldsName(&$data)
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->__normalizeFieldsName($value);
            }
            if (strpos($key, '.') !== false) {
                list($collection, $field) = explode('.', $key);
                $data[$field] = $value;
                unset($data[$key]);
            }
        }
    }

    /**
     * convert sql conditions into mongodb conditions
     *
     * '!=' => '$ne',
     * '>' => '$gt',
     * '>=' => '$gte',
     * '<' => '$lt',
     * '<=' => '$lte',
     * 'IN' => '$in',
     * 'NOT' => '$not',
     * 'NOT IN' => '$nin'
     *
     * @param array $conditions
     * @access private
     * @return array
     */
    private function __translateConditions(&$conditions)
    {
        $operators = '<|>|<=|>=|!=|=|<>|NOT IN|NOT LIKE|IN|LIKE';
        foreach ($conditions as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                $this->__translateConditions($conditions[$key]);
            } elseif (preg_match("/^(.+) ($operators)$/", $key, $matches)) {
                list(, $field, $operator) = $matches;
                $operator = $this->__translateOperator(strtoupper($operator));
                unset($conditions[$key]);
                if (substr($operator, -4) === 'LIKE') {
                    $value = str_replace('%', '.*', $value);
                    $value = str_replace('?', '.', $value);
                    if ($operator === 'NOT LIKE') {
                        $value = "(?!$value)";
                    }
                    $operator = '$regex';
                    $value = new \MongoDB\BSON\Regex("^$value$", "i");
                }
                $conditions[$field][$operator] = $value;
            } elseif (preg_match('/^OR|AND|NOT$/i', $key, $match)) {
                $operator = '$'.strtolower($match[0]);
                unset($conditions[$key]);
                foreach ($value as $nestedKey => $nestedValue) {
                    if (!is_array($nestedValue)) {
                        $nestedValue = [$nestedKey => $nestedValue];
                        $conditions[$operator][$nestedKey] = $nestedValue;
                    } else {
                        $conditions[$operator][$nestedKey] = $nestedValue;
                    }
                    $this->__translateConditions($conditions[$operator][$nestedKey]);

                }
            } elseif (preg_match("/^(.+) (<|>|<=|>=|!=|=) (.+)$/", $key, $matches)
                || (is_string($value) && preg_match("/^(.+) (<|>|<=|>=|!=|=) (.+)$/", $value, $matches))
            ) {
                unset($conditions[$key]);
                array_splice($matches, 0, 1);
                $conditions['$where'] = implode(' ', array_map(function($v) {
                    if (preg_match("/^[\w.]+$/", $v)
                        && substr($v, 0, strlen('this')) !== 'this'
                    ) {
                        $v = "this.$v";
                    }
                    return $v;
                }, $matches));
            }
        }

        return $conditions;
    }

    /**
     * Convert logical operator to MongoDB Query Selectors
     * @param string $operator
     * @return string
     */
    private function __translateOperator($operator)
    {
        switch ($operator) {
            case '<': return '$lt';
            case '<=': return '$lte';
            case '>': return '$gt';
            case '>=': return '$gte';
            case '=': return '$eq';
            case '!=':
            case '<>': return '$ne';
            case 'NOT IN': return '$nin';
            case 'IN': return '$in';
            default: return $operator;
        }
    }

    /**
     * try to find documents
     *
     * @return \MongoDB\Driver\Cursor $cursor
     * @access public
     */
    public function find()
    {
        $cursor = $this->connection()->find($this->_options['where'], $this->_options['fields']);
        $this->_totalRows = count($cursor);

        if ($this->_totalRows > 0) {
            if (!empty($this->_options['order'])) {
                foreach ($this->_options['order'] as $field => $direction) {
                    $sort[$field] = $direction == 'asc' ? 1 : -1;
                }

                $cursor->sort($sort);
            }

            if (!empty($this->_options['page']) && $this->_options['page'] > 1) {
                $skip = $this->_options['limit'] * ($this->_options['page'] - 1);
                $cursor->skip($skip);
            }

            if (!empty($this->_options['limit'])) {
                $cursor->limit($this->_options['limit']);
            }
        }

        return $cursor;
    }

    /**
     * return all documents
     *
     * @return \MongoDB\Driver\Cursor
     * @access public
     */
    public function findAll()
    {
        return $this->find();
    }

    /**
     * return all documents
     *
     * @return \MongoDB\Driver\Cursor
     * @access public
     */
    public function findList()
    {
        return $this->find();
    }

    /**
     * return document with _id = $primaKey
     *
     * @param string $primaryKey
     * @return \MongoDB\Driver\Cursor
     * @access public
     */
    public function get($primaryKey)
    {
        $this->_options['where']['_id'] = new \MongoDB\BSON\ObjectId($primaryKey);

        return $this->find();
    }

    /**
     * return number of rows finded
     *
     * @return int
     * @access public
     */
    public function count()
    {
        return $this->_totalRows;
    }
}
