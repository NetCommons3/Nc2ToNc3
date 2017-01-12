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
 * List of migration message.
 *
 * @var mix
 */
	public $migrationMessages = null;

/**
 * List of migration message.
 *
 * @var mix
 */
	public $pathConfig = null;

/**
 * Setup this behavior with the specified configuration settings.
 *
 * @param Model $model Model using this behavior
 * @param array $config Configuration settings for $model
 * @return void
 */
	public function setup(Model $model, $config = array()) {
		CakeLog::config('Nc2ToNc3', array(
			'engine' => 'FileLog',
			'scopes' => ['Nc2ToNc3'],
			'file' => 'Nc2ToNc3.log',
		));
	}

/**
 * Set migration message
 *
 * @param Model $model Model using this behavior.
 * @param string $message Migration message
 * @return void
 */
	public function setMigrationMessages(Model $model, $message) {
		$this->_setMigrationMessages($message);
	}

/**
 * Get migration message
 *
 * @param Model $model Model using this behavior.
 * @return string Migration messages
 */
	public function getMigrationMessages(Model $model) {
		return $this->_getMigrationMessages();
	}

/**
 * Set path config
 *
 * @param Model $model Model using this behavior.
 * @param string $config Migration config
 * @return void
 */
	public function setPathConfig(Model $model, $config) {
		$this->_setPathConfig($config);
	}

/**
 * Get path config
 *
 * @param Model $model Model using this behavior.
 * @return string Migration messages
 */
	public function getPathConfig(Model $model) {
		return $this->_getPathConfig();
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
 * Set migration message
 *
 * @param string $message Migration message
 * @return void
 */
	protected function _setMigrationMessages($message) {
		// 配列に追加とかも思ったが、とりあえず最後のエラーのみセットしとく
		$this->migrationMessages = $message;
	}

/**
 * Get migration message
 *
 * @return string Migration messages
 */
	protected function _getMigrationMessages() {
		return $this->migrationMessages;
	}

/**
 * Set path config
 *
 * @param string $config path config
 * @return void
 */
	protected function _setPathConfig($config) {
		$this->pathConfig['upload_path'] = $config['upload_path'];
		$this->pathConfig['items_ini_path'] = $config['items_ini_path'];
	}

/**
 * Get path config
 *
 * @return string Migration messages
 */
	protected function _getPathConfig() {
		return $this->pathConfig;
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
			CakeLog::write('Nc2ToNc3', $message . ' : ' . $debugString);
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

		CakeLog::write('Nc2ToNc3', $message);
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

}
