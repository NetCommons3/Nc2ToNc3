<?php
/**
 * Nc2ToNc3MessageBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('ModelBehavior', 'Model');

/**
 * Nc2ToNc3MessageBehavior
 *
 */
class Nc2ToNc3MessageBehavior extends ModelBehavior {

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
}
