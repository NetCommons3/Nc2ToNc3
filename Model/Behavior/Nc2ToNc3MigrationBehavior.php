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
class Nc2ToNc3MigrationBehavior extends ModelBehavior {

/**
 * List of migration message.
 *
 * @var mix
 */
	public $migrationMessages = null;

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
		// 配列に追加とかも思ったが、とりあえず最後のエラーのみセットしとく
		$this->migrationMessages = $message;
	}

/**
 * Get migration message
 *
 * @param Model $model Model using this behavior.
 * @return void
 */
	public function getMigrationMessages(Model $model) {
		return $this->migrationMessages;
	}

/**
 * Write migration log
 *
 * @param Model $model Model using this behavior.
 * @param string $message Migration message
 * @return void
 */
	public function writeMigrationLog(Model $model, $message) {
		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
		if (isset($backtraces[4]) &&
			isset($backtraces[4]['line']) &&
			isset($backtraces[4]['class']) &&
			$backtraces[4]['function'] == 'writeMigrationLog'
		) {
			$message = $message . ' : ' . $backtraces[4]['class'] . ' on line ' . $backtraces[4]['line'];
		}

		CakeLog::write('Nc2ToNc3', $message);
	}

/**
 * Get Nc2 Model
 *
 * @param Model $model Model using this behavior.
 * @param string $tableName Nc2 table name
 * @return Model Nc2 model
 */
	public static function getNc2Model(Model $model, $tableName) {
		$alias = 'Nc2' . Inflector::classify($tableName);
		$Molde = ClassRegistry::getObject($alias);
		if ($Molde) {
			$Molde->useDbConfig = Nc2ToNc3::CONNECTION_NAME;
			return $Molde;
		}

		// クラス自体は存在しない。
		// Nc2ToNc3AppModelのインスタンスを作成し返す。
		// Nc2ToNc3AppModelはNetCommonsAppModelを継承しない。
		$class = 'Nc2ToNc3.Nc2' . $tableName;
		$Molde = ClassRegistry::init([
			'class' => $class,
			'table' => $tableName,
			'alias' => $alias,
			'ds' => Nc2ToNc3::CONNECTION_NAME
		]);

		return $Molde;
	}

/**
 * Get Nc3 Model
 *
 * @param Model $model Model using this behavior.
 * @param string $class Instance will be created,stored in the registry and returned.
 * @return Model Nc2 model
 */
	public static function getNc3Model(Model $model, $class) {
		list(, $alias) = pluginSplit($class);
		$Molde = ClassRegistry::getObject($alias);
		if ($Molde) {
			return $Molde;
		}

		return ClassRegistry::init($class);
	}
}
