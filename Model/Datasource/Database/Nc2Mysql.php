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

/**
 * Override parent::column
 *
 * Remove ($col === 'tinyint' && $limit === 1) check
 *
 * @param string $real Real database-layer column type (i.e. "varchar(255)")
 * @return string Abstract column type (i.e. "string")
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
	public function column($real) {
		if (is_array($real)) {
			$col = $real['name'];
			if (isset($real['limit'])) {
				$col .= '(' . $real['limit'] . ')';
			}
			return $col;
		}

		$col = str_replace(')', '', $real);
		if (strpos($col, '(') !== false) {
			list($col, $vals) = explode('(', $col);
		}

		if (in_array($col, array('date', 'time', 'datetime', 'timestamp'))) {
			return $col;
		}
		if ($col === 'boolean') {
			return 'boolean';
		}
		if (strpos($col, 'bigint') !== false || $col === 'bigint') {
			return 'biginteger';
		}
		if (strpos($col, 'int') !== false) {
			return 'integer';
		}
		if (strpos($col, 'char') !== false || $col === 'tinytext') {
			return 'string';
		}
		if (strpos($col, 'text') !== false) {
			return 'text';
		}
		if (strpos($col, 'blob') !== false || $col === 'binary') {
			return 'binary';
		}
		if (strpos($col, 'float') !== false || strpos($col, 'double') !== false) {
			return 'float';
		}
		if (strpos($col, 'decimal') !== false || strpos($col, 'numeric') !== false) {
			return 'decimal';
		}
		if (strpos($col, 'enum') !== false) {
			return "enum($vals)";
		}
		if (strpos($col, 'set') !== false) {
			return "set($vals)";
		}
		return 'text';
	}

}
