<?php
/**
 * Nc2ToNc3UserAttribute
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('UserAttribute', 'UserAttributes.Model');

/**
 * Nc2ToNc3UserAttribute
 *
 */
class Nc2ToNc3UserAttribute extends UserAttribute {

/**
 * Custom database table name, or null/false if no table association is desired.
 *
 * @var string
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#usetable
 */
	public $useTable = 'user_attributes';

/**
 * Alias name for model.
 *
 * @var string
 */
	public $alias = 'UserAttribute';

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Migration'];

/**
 * Mapping nc2 tag to nc3 key
 *
 * @var array
 */
	private static $__mappingTagToKey = [
		'email' => 'email',
		'lang_dirname_lang' => 'language',
		'timezone_offset_lang' => 'timezone',
		'role_authority_name' => 'role_key',
		'active_flag_lang' => 'status',
		'login_id' => 'username',
		'password' => 'password',
		'handle' => 'handlename',
		'user_name' => 'name',
		'password_regist_time' => 'password_modified',
		'last_login_time' => 'last_login',
		'previous_login_time' => 'previous_login',
		'insert_time' => 'created',
		'insert_user_name' => 'created_user',
		'update_time' => 'modified',
		'update_user_name' => 'modified_user',
	];

/**
 * Mapping nc2 id to nc3 id
 * ユーザー情報で使う予定なのでpublic
 *
 * @var array
 */
	public $mappingId = [];

/**
 * Language id from Nc2
 *
 * @var array
 */
	private $__languageIdFromNc2 = null;

/**
 * Migration
 *
 * @return bool True on success
 * @throws InternalErrorException
 */
	public function migrate() {
		if (!$this->validateNc2()) {
			return false;
		}

		if (!$this->validateNc3()) {
			return false;
		}

		if (!$this->saveUserAttributeFromNc2()) {
			return false;
		}

		if (!$this->setMappingDataNc2ToNc3()) {
			return false;
		}

		return true;
	}

/**
 * Validate Nc2_items data
 *
 * @return bool True on success
 */
	public function validateNc2() {
		return true;
	}

/**
 * Validate UserAttribue data
 *
 * @return bool True on success
 */
	public function validateNc3() {
		// cakeのvalidation使った方が良いか？

		/*
		// 無ければ移行するようにしたので不要
		$keyList = $this->__getDefaultKeyList();
		$defaultSystemKeys = [
			'avatar',
			'language',
			'timezone',
			'role_key',
			'status',
			'username',
			'password',
			'handlename',
			'password_modified',
			'last_login',
			'previous_login',
			'created',
			'created_user',
			'modified',
			'modified_user'
		];

		$diff = array_diff($defaultSystemKeys, $keyList);
		if (!empty($diff)) {
			$this->setMigrationMessages(__d('nc2_to_nc3', 'Existing user attribute data is invalid.'));
			return false;
		}
		*/

		return true;
	}

/**
 * Save UserAttribue from Nc2
 *
 * @return bool True on success
 */
	public function saveUserAttributeFromNc2() {
		$this->__setLanguageIdFromNc2();

		$Nc2Item = $this->getNc2Model('items');
		$query = [
			'order' => [
				'Item.col_num',
				'Item.row_num'
			]
		];
		$nc2Items = $Nc2Item->find('all', $query);

		foreach ($nc2Items as $nc2Item) {
			if (!$this->__isMigrationRow($nc2Item)) {
				//var_dump($nc2Item['Item']);
				continue;
			}
			//var_dump(99, $nc2Item['Item']);
		}

		/*
		$query = [
			'conditions' => [
				'NOT' => ['Item.tag_name' => []
					'mobile_texthtml_mode',
					'mobile_imgdsp_size',
					'userinf_view_main_room',
					'userinf_view_main_monthly',
					'userinf_view_main_modulesinfo',
				],
			'recursive' => -1
		];
		$block = $Block->find('first', $query);


		// NC3に既存の項目は移行しない
		$this->

		var_dump($Nc2Item->find('first'));

		data[UserAttributeSetting][id]:
		data[UserAttributeSetting][row]:1
		data[UserAttributeSetting][col]:2
		data[UserAttributeSetting][weight]:
		data[UserAttributeSetting][display]:1
		data[UserAttributeSetting][is_system]:0
		data[UserAttributeSetting][user_attribute_key]:
		data[UserAttribute][0][id]:
		data[UserAttribute][0][key]:
		data[UserAttribute][0][language_id]:1
		data[UserAttribute][0][name]:
		data[UserAttribute][1][id]:
		data[UserAttribute][1][key]:
		data[UserAttribute][1][language_id]:2
		data[UserAttribute][1][name]:
		data[UserAttributeSetting][display_label]:0
		data[UserAttributeSetting][display_label]:1
		data[UserAttributeSetting][data_type_key]:text
		data[UserAttributeSetting][is_multilingualization]:0
		data[UserAttributeSetting][is_multilingualization]:1
		data[UserAttributeSetting][required]:0
		data[UserAttributeSetting][only_administrator_readable]:0
		data[UserAttributeSetting][only_administrator_editable]:0
		data[UserAttributeSetting][self_public_setting]:0
		data[UserAttributeSetting][self_email_setting]:0
		data[UserAttribute][0][description]:
		data[UserAttribute][1][description]:

		$this->saveUserAttribute($data);
		*/
		return true;
	}

/**
 * Set mapping data
 *
 * @return bool True on success
 */
	public function setMappingDataNc2ToNc3() {
		return true;
	}

/**
 * Find default key list
 *
 * @return array Default UserAttribue data
 */
	private function __getDefaultKeyList() {
		static $keyList = array();
		if (!empty($keyList)) {
			return $keyList;
		}

		$defaultKeys = [
			'avatar',
			'email',
			'moblie_mail',
			'sex',
			'language',
			'timezone',
			'role_key',
			'status',
			'username',
			'password',
			'handlename',
			'name',
			'password_modified',
			'last_login',
			'previous_login',
			'created',
			'created_user',
			'modified',
			'modified_user',
			'profile',
			'search_keywords'
		];

		$query = [
			'fields' => ['UserAttribute.key'],
			'conditions' => [
				'UserAttribute.key' => $defaultKeys,
				'UserAttribute.language_id' => $this->__languageIdFromNc2,
			],
			'recursive' => -1
		];
		$keyList = $this->find('list', $query);

		return $keyList;
	}

/**
 * Check migration target
 *
 * @param array $nc2Item nc2 item data
 * @return bool True if data is migration target
 */
	private function __isMigrationRow($nc2Item) {
		$tagName = $nc2Item['Item']['tag_name'];
		$notMigrationTagNames = [
			'mobile_texthtml_mode',
			'mobile_imgdsp_size',
			'userinf_view_main_room',
			'userinf_view_main_monthly',
			'userinf_view_main_modulesinfo'
		];
		if (in_array($tagName, $notMigrationTagNames)) {
			return false;
		}

		$keyList = $this->__getDefaultKeyList();
		$defaultTagNames = [
			'email',
			'lang_dirname_lang',
			'timezone_offset_lang',
			'role_authority_name',
			'active_flag_lang',
			'login_id',
			'password',
			'handle',
			'user_name',
			'password_regist_time',
			'last_login_time',
			'previous_login_time',
			'insert_time',
			'insert_user_name',
			'update_time',
			'update_user_name',
		];
		$nc3Id = null;
		if (in_array($tagName, $defaultTagNames)) {
			$nc3Id = array_search(static::$__mappingTagToKey[$tagName], $keyList);
		}

		$itemName = $nc2Item['Item']['item_name'];
		switch ($itemName) {
			case 'USER_ITEM_AVATAR':
				$nc3Id = array_search('avatar', $keyList);
				break;

			case 'USER_ITEM_MOBILE_EMAIL':
				$nc3Id = array_search('moblie_mail', $keyList);
				break;

			case 'USER_ITEM_GENDER':
				$nc3Id = array_search('sex', $keyList);
				break;

			case 'USER_ITEM_PROFILE':
				$nc3Id = array_search('profile', $keyList);
				break;

		}

		if ($nc3Id) {
			$nc2Id = $nc2Item['Item']['item_id'];
			$this->mappingId[$nc2Id] = $nc3Id;

			return false;
		}

		$userAttribute = $this->findByNameAndLanguageId($itemName, $this->__languageIdFromNc2, 'id', null, -1);
		if ($userAttribute) {
			$nc2Id = $nc2Item['Item']['item_id'];
			$this->mappingId[$nc2Id] = $userAttribute['UserAttribute']['id'];

			return false;
		}

		return true;
	}

/**
 * Set language id from Nc2
 *
 * @return void
 */
	private function __setLanguageIdFromNc2() {
		$Nc2Config = $this->getNc2Model('config');
		$configData = $Nc2Config->findByConfName('language', 'conf_value', null, -1);

		$language = $configData['Config']['conf_value'];
		switch ($language) {
			case 'english':
				$code = 'en';
				break;

			default:
				$code = 'ja';

		}

		$Language = ClassRegistry::init('M17n.Language');
		$language = $Language->findByCode($code, 'id', null, -1);
		$this->__languageIdFromNc2 = $language['Language']['id'];
	}
}
