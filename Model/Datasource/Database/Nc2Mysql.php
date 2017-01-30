<?php
/**
 * Nc2Mysql
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Mysql', 'Model/Datasource/Database');

/**
 * MySQL DBO driver object for Nc2
 *
 */
class Nc2Mysql extends Mysql {

/**
 * Override parent::resultSet
 *
 * Remove $column['native_type'] === 'TINY' check
 *
 * @param PDOStatement $results The results to format.
 * @return void
 */
	public function resultSet($results) {
		$this->map = array();
		$numFields = $results->columnCount();
		$index = 0;

		while ($numFields-- > 0) {
			$column = $results->getColumnMeta($index);
			if ($column['len'] === 1 && empty($column['native_type'])) {
				$type = 'boolean';
			} else {
				$type = empty($column['native_type']) ? 'string' : $column['native_type'];
			}
			if (!empty($column['table']) && strpos($column['name'], $this->virtualFieldSeparator) === false) {
				$this->map[$index++] = array($column['table'], $column['name'], $type);
			} else {
				$this->map[$index++] = array(0, $column['name'], $type);
			}
		}
	}

}
