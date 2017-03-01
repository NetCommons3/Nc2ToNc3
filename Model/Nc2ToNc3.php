<?php
/**
 * Nc2ToNc3
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3
 *
 * Data array sample
 * $data['database'] => 'nc2421'
 * $data['prefix'] => 'nc_'
 * $data['datasource'] => 'Database/Mysql'
 * $data['host'] => 'localhost'
 * $data['port'] => '3306'
 * $data['login'] => ''
 * $data['password'] => ''
 * $data['persistent'] => ''
 * $data['encoding'] => 'utf8'
 * $data['schema'] => 'public'
 * $data['upload_path'] => ''
 * $data['items_ini_path'] => ''
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 */
class Nc2ToNc3 extends Nc2ToNc3AppModel {

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const CONNECTION_NAME = 'nc2';

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const VALID_VERSION = '2.4.2.1';

/**
 * Custom database table name, or null/false if no table association is desired.
 *
 * @var string
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#usetable
 */
	public $useTable = false;

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Base'];

/**
 * Called during validation operations, before validation. Please note that custom
 * validation rules can be defined in $validate.
 *
 * @param array $options Options passed from Model::save().
 * @return bool True if validate operation should continue, false to abort
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#beforevalidate
 * @see Model::save()
 */
	public function beforeValidate($options = array()) {
		$this->validate = Hash::merge(
			$this->validate,
			[
				'upload_path' => [
					'notBlank' => [
						'rule' => ['notBlank'],
						'message' => sprintf(
							__d('net_commons', 'Please input %s.'), __d('nc2_to_nc3', 'Upload file path')
						),
					'allowEmpty' => false,
					'required' => true,
					],
					'isUploadPath' => [
						'rule' => ['isUploadPath'],
						'message' => sprintf(
							__d('nc2_to_nc3', 'The above Upload file path does not exist.')
						),
					]
				],
				'database' => [
					'notBlank' => [
						'rule' => ['notBlank'],
						'message' => sprintf(
							__d('net_commons', 'Please input %s.'), __d('nc2_to_nc3', 'Database')
						),
						'allowEmpty' => false,
						'required' => true,
					],
					'canConnectNc2' => [
						'rule' => ['canConnectNc2'],
						// 'message' => canConnectNc2でException::getMessage()を返す
					],
					'isValidNc2Data' => [
						'rule' => ['isValidNc2Data'],
						// 'message' => isValidNc2Dataでメッセージを返す
					],
					// TODOーNC3のバージョン、状態（サイト閉鎖）をチェック
				],
			]
		);

		return parent::beforeValidate($options);
	}

/**
 * Validate to create a DataSource object for nc2
 *
 * @return string|bool True on it access to nc2 database
 */
	public function canConnectNc2() {
		$config = $this->data['Nc2ToNc3'];
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		$config += $nc3config;
		$config['datasource'] = 'Nc2ToNc3.Database/Nc2Mysql';

		// DataSource情報が間違っている場合、Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			ConnectionManager::create(static::CONNECTION_NAME, $config);
		} catch (Exception $ex) {
			CakeLog::error($ex);
			return $ex->getMessage();
		}
		return true;
	}

/**
 * Validate DataSource object for nc2.
 *
 * @return string|bool True on it access to config table of nc2.
 */
	public function isValidNc2Data() {
		/* @var $Nc2Config AppModel */
		$Nc2Config = $this->getNc2Model('config');

		// DataSource情報(prefix)が間違っている場合、Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			// 対象バージョンチェック
			$configData = $Nc2Config->findByConfName('version', 'conf_value', null, -1);
			if ($configData['Nc2Config']['conf_value'] != static::VALID_VERSION) {
				ConnectionManager::drop(static::CONNECTION_NAME);
				return __d('nc2_to_nc3', 'NetCommons2 version is not %s', static::VALID_VERSION);
			}

			// サイト閉鎖チェックはダンプデータをインポートしたDBを考慮するとしない方が良いのでは？
			// 運用中のDBを対象にしないことを推奨する
			//$configData = $Nc2Config->findByConfName('closesite', 'conf_value', null, -1);

		} catch (Exception $ex) {
			CakeLog::error($ex);
			return __d('nc2_to_nc3', 'NetCommons2 table is not found.');
		}

		return true;
	}

/**
 * Initializes the model for writing a new record, loading the default values
 * for those fields that are not defined in $data, and clearing previous validation errors.
 * Especially helpful for saving data in loops.
 * Not call parent::create, so the parameter is unnecessary.
 *
 * @param bool|array $data Optional data array to assign to the model after it is created. If null or false,
 *   schema data defaults are not merged.
 * @param bool $filterKey If true, overwrites any primary key input with an empty value
 * @return array The current Model::data; defaults from NetCommons3 DataSource
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
	public function create($data = array(), $filterKey = false) {
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		unset($nc3config['database'], $nc3config['prefix']);

		// TODOー開発用データ
		$nc3config['database'] = 'nc2421';
		$nc3config['prefix'] = 'nc_';
		$nc3config['upload_path'] = '/var/www/html/NC2421/webapp/uploads/';

		return $nc3config;
	}

/**
 * Migration
 *
 * @param array $data received post data
 * @return bool True on success
 */
	public function migration($data) {
		$this->set($data);

		if (!$this->validates()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration start.'));

		/* @var $Nc2ToNc3UserAttr Nc2ToNc3UserAttribute */
		$Nc2ToNc3UserAttr = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserAttribute');
		if (!$Nc2ToNc3UserAttr->migrate()) {
			$this->validationErrors = $Nc2ToNc3UserAttr->validationErrors;
			return false;
		}

		// @see Nc2ToNc3UserAttribute::calledCakeMigration
		if ($Nc2ToNc3UserAttr->calledCakeMigration) {
			ClassRegistry::addObject('Nc2ToNc3', $this);
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		if (!$Nc2ToNc3User->migrate()) {
			$this->validationErrors = $Nc2ToNc3User->validationErrors;
			return false;
		}

		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		if (!$Nc2ToNc3Room->migrate()) {
			$this->validationErrors = $Nc2ToNc3Room->validationErrors;
			return false;
		}

		/* @var $Nc2ToNc3Page Nc2ToNc3Page */
		$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');
		if (!$Nc2ToNc3Page->migrate()) {
			$this->validationErrors = $Nc2ToNc3Page->validationErrors;
			return false;
		}

		/* @var $Nc2ToNc3Announcement Nc2ToNc3Announcement */
		$Nc2ToNc3Announcement = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Announcement');
		if (!$Nc2ToNc3Announcement->migrate()) {
			$this->validationErrors = $Nc2ToNc3Announcement->validationErrors;
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration end.'));

		return true;
	}

/**
 * Validate Upload Path for nc2.
 *
 * @return string|bool True on it access to config table of nc2.
 */
	public function isUploadPath() {
		$config = $this->data['Nc2ToNc3'];
		$nc2UploadPath = $config['upload_path'];

		return is_dir($nc2UploadPath);
	}
}

