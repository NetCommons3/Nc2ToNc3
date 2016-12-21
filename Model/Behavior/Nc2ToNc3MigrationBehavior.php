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
 * Get Nc2 Model
 *
 * @param Model $model Model using this behavior.
 * @param string $tableName Nc2 table name
 * @return Model Nc2 model
 */
	public static function getNc2Model(Model $model, $tableName) {
		$alias = Inflector::classify($tableName);
		$Molde = ClassRegistry::getObject($alias);
		if ($Molde) {
			return $Molde;
		}

		$class = 'Nc2' . $tableName;
		$Molde = ClassRegistry::init([
			'class' => $class,
			'table' => $tableName,
			'alias' => $alias,
			'ds' => Nc2ToNc3::CONNECTION_NAME
		]);

		return $Molde;
	}
}
