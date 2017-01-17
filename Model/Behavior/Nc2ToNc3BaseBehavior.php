<?php
/**
 * Nc2ToNc3MigrationBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('ModelBehavior', 'Model');
App::uses('Nc2ToNc3', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3MigrationBehavior
 *
 */
class Nc2ToNc3BaseBehavior extends ModelBehavior {

/**
 * Setup this behavior with the specified configuration settings.
 *
 * @param Model $model Model using this behavior
 * @param array $config Configuration settings for $model
 * @return void
 */
	public function setup(Model $model, $config = array()) {
		// Nc2ToNc3BaseBehavior::_writeMigrationLogでログ出力している
		// CakeLog::writeでファイルとコンソールに出力していた。
		// Consoleに出力すると<tag></tag>で囲われ見辛い。
		// @see
		// https://github.com/cakephp/cakephp/blob/2.9.4/lib/Cake/Console/ConsoleOutput.php#L230-L241
		// CakeLog::infoをよびだし、debug.logとNc2ToNc3.logの両方出力するようにした。
		CakeLog::config(
			'Nc2ToNc3File',
			[
				'engine' => 'FileLog',
				'types' => ['info'],
				'scopes' => ['Nc2ToNc3'],
				'file' => 'Nc2ToNc3.log',
			]
		);
	}

/**
 * Write migration log
 *
 * @param Model $model Model using this behavior.
 * @param string $message Migration message
 * @return void
 */
	public function writeMigrationLog(Model $model, $message) {
		$debugString = '';
		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
		if (isset($backtraces[4]) &&
			isset($backtraces[4]['line']) &&
			isset($backtraces[4]['class']) &&
			$backtraces[4]['function'] == 'writeMigrationLog'
		) {
			$debugString = $backtraces[4]['class'] . ' on line ' . $backtraces[4]['line'];
		}

		$this->_writeMigrationLog($message, $debugString);
	}

/**
 * Get Nc2 Model
 *
 * @param Model $model Model using this behavior.
 * @param string $tableName Nc2 table name
 * @return Model Nc2 model
 */
	public function getNc2Model(Model $model, $tableName) {
		return $this->_getNc2Model($tableName);
	}

/**
 * Get convert date
 *
 * @param Model $model Model using this behavior.
 * @param string $date Nc2 date
 * @return Model convert date
 */
	public function getConvertDate(Model $model, $date) {
		return $this->_getConvertDate($date);
	}

/**
 * Write migration log
 *
 * @param string $message Migration message
 * @param string $debugString Debug string
 * @return void
 */
	protected function _writeMigrationLog($message, $debugString = '') {
		if ($debugString) {
			CakeLog::info($message . ' : ' . $debugString, ['Nc2ToNc3']);
			return;
		}

		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		if (isset($backtraces[0]) &&
			isset($backtraces[0]['line']) &&
			isset($backtraces[1]['class']) &&
			$backtraces[0]['function'] == '_writeMigrationLog'
		) {
			$message = $message . ' : ' . $backtraces[1]['class'] . ' on line ' . $backtraces[0]['line'];
		}

		CakeLog::info($message, ['Nc2ToNc3']);
	}

/**
 * Get Nc2 Model
 *
 * @param string $tableName Nc2 table name
 * @return Model Nc2 model
 */
	protected function _getNc2Model($tableName) {
		// クラス自体は存在しない。
		// Nc2ToNc3AppModelのインスタンスを作成し返す。
		// Nc2ToNc3AppModelはNetCommonsAppModelを継承しない。
		$Molde = ClassRegistry::init([
			'class' => 'Nc2ToNc3.Nc2' . $tableName,
			'table' => $tableName,
			'alias' => 'Nc2' . Inflector::classify($tableName),
			'ds' => Nc2ToNc3::CONNECTION_NAME
		]);

		return $Molde;
	}

/**
 * Get convert date
 *
 * @param string $date Nc2 date
 * @return Model convert date
 */
	protected function _getConvertDate($date) {
		if (strlen($date) != 14) {
			return null;
		}

		// YmdHis → Y-m-d H:i:s　
		$date = substr($date, 0, 4) . '-' .
				substr($date, 4, 2) . '-' .
				substr($date, 6, 2) . ' ' .
				substr($date, 8, 2) . ':' .
				substr($date, 10, 2) . ':' .
				substr($date, 12, 2);

		return $date;
	}

}
