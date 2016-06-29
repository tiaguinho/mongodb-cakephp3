<?php

namespace Hayko\Mongodb\ORM;

class ResultSet {

	/**
	 * store the convertion of mongo cursor into array
	 * 
	 * @var array $_results
	 * @access protected
	 */
		protected $_results;

	/**
	 * table name
	 * 
	 * @var string $_table
	 * @access protected
	 */
		protected $_table;

	/**
	 * set results and table name
	 * 
	 * @param \MongoCursor $cursor
	 * @param string $table
	 * @access public
	 */
		public function __construct(\MongoCursor $cursor, $table) {
			$this->_results = iterator_to_array($cursor);
			$this->_table = $table;
		}

	/**
	 * convert mongo documents in cake entitys
	 * 
	 * @return []Cake\ORM\Entity $results
	 * @access public
	 */
		public function toArray() {
			$results = [];
			foreach ($this->_results as $result) {
				$document = new Document($result, $this->_table);
				$results[] = $document->cakefy();
			}

			return $results;
		}

}