<?php
/**
 * AddRecord Class
 *
 */

App::uses('NetCommonsMigration', 'NetCommons.Config/Migration');

/**
 * AddRecord Class
 *
 */
class AddRecord extends NetCommonsMigration {

/**
 * Migration description
 *
 * @var string
 */
	public $description = 'add_record';

/**
 * Actions to be performed
 *
 * @var array $migration
 */
	public $migration = array(
		'up' => array(
		),
		'down' => array(
		),
	);

/**
 * plugin data
 *
 * @var array $migration
 */
	public $records = array(
		'Plugin' => array(
			//日本語
			array(
				'language_id' => '2',
				'key' => 'nc2_to_nc3',
				'namespace' => 'netcommons/nc2-to-nc3',
				'name' => 'NC2からの移行',
				'type' => 3,
				'default_action' => 'nc2_to_nc3/migration',
				'default_setting_action' => '',
				'weight' => 9,
			),
			//英語
			array(
				'language_id' => '1',
				'key' => 'nc2_to_nc3',
				'namespace' => 'netcommons/nc2-to-nc3',
				'name' => 'Migration From NC2',
				'type' => 3,
				'default_action' => 'nc2_to_nc3/migration',
				'default_setting_action' => '',
				'weight' => 9,
			),
		),
		'PluginsRole' => array(
			array(
				'role_key' => 'system_administrator',
				'plugin_key' => 'nc2_to_nc3',
			),
		),
	);

/**
 * Before migration callback
 *
 * @param string $direction Direction of migration process (up or down)
 * @return bool Should process continue
 */
	public function before($direction) {
		return true;
	}

/**
 * After migration callback
 *
 * @param string $direction Direction of migration process (up or down)
 * @return bool Should process continue
 */
	public function after($direction) {
		$this->loadModels([
			'Plugin' => 'PluginManager.Plugin',
		]);

		if ($direction === 'down') {
			$this->Plugin->uninstallPlugin($this->records['Plugin'][0]['key']);
			return true;
		}

		foreach ($this->records as $model => $records) {
			if (!$this->updateRecords($model, $records)) {
				return false;
			}
		}

		return true;
	}
}
