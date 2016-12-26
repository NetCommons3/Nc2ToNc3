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
 * Mapping nc2 id to nc3 id.
 * ユーザー情報で使う予定なのでpublic
 *
 * @var array
 */
	public $mappingId = [];

/**
 * Language id from Nc2.
 *
 * @var array
 */
	private $__languageIdFromNc2 = null;

/**
 * Migration method.
 *
 * @return bool True on success
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
 * Validate nc2 items data.
 *
 * @return bool True if nc2 data is valid.
 * @see Nc2ToNc3UserAttribute::__isMigrationRow()
 */
	public function validateNc2() {
		// 不正データは移行処理をしないようにした
		return true;
	}

/**
 * Validate Nc3 UserAttribue data
 *
 * @return bool bool True if nc3 data is valid.
 * @see Nc2ToNc3UserAttribute::__isMigrationRow()
 */
	public function validateNc3() {
		// Nc3に存在しなれば移行するようにした
		return true;
	}

/**
 * Save UserAttribue from Nc2.
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
		$migratedNc2Ids = [];

		// NC3に存在しない会員項目の作成ループ
		foreach ($nc2Items as $nc2Item) {
			if (!$this->__isMigrationRow($nc2Item)) {
				//var_dump($nc2Item['Item']);
				continue;
			}
			//var_dump(99, $nc2Item['Item']);
			continue;
			$data = $this->__generateNc3Data($nc2Item);

			if (!$this->saveUserAttribute($data)) {
				// error
			}

			$migratedNc2Ids[] = $nc2Item['Item']['item_id'];
		}
		//var_dump($this->mappingId);
		// 選択肢項目のマージループ
		/*
			$choice'radio'
				'checkbox'
				'select'
		foreach ($nc2Items as $nc2Item) {
			if (in_array($nc2Item['Item']['item_id'], $migratedNc2Ids)) {
				continue;
			}

			if ($nc2Item['Item']['item_id']) {
				continue;
			}

			$data = $this->__generateNc3Data($nc2Item);

			//var_dump(99, $nc2Item['Item']);

			$this->saveUserAttribute($data);
		}
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
			// TODOー対象外のLogを出力
			return false;
		}

		$dataTypeKey = $this->__convertNc2Type($nc2Item);
		if (!$dataTypeKey) {
			// TODOー$nc2Item['Item']['type']が不正なLogを出力
			return false;
		}

		$nc3Id = $this->__getNc3UserAttributeIdByTagNameAndDataTypeKey($nc2Item, $dataTypeKey);
		if ($nc3Id) {
			$nc2Id = $nc2Item['Item']['item_id'];
			$this->mappingId[$nc2Id] = $nc3Id;

			return false;
		}

		$nc3Id = $this->__getNc3UserAttributeIdByDefaultItemNameAndDataTypeKey($nc2Item, $dataTypeKey);
		if ($nc3Id) {
			$nc2Id = $nc2Item['Item']['item_id'];
			$this->mappingId[$nc2Id] = $nc3Id;

			return false;
		}

		$nc3Id = $this->__getNc3UserAttributeIdByItemNameAndDataTypeKey($nc2Item, $dataTypeKey);
		if ($nc3Id) {
			$nc2Id = $nc2Item['Item']['item_id'];
			$this->mappingId[$nc2Id] = $nc3Id;

			return false;
		}

		return true;
	}

/**
 * Convert Nc2 type
 * If invalid type, return ''
 *
 * @param array $nc2Item nc2 item data
 * @return string Converted Nc2 type
 */
	private function __convertNc2Type($nc2Item) {
		if ($nc2Item['Item']['type'] == 'mobile_email') {
			return 'email';
		}

		if ($nc2Item['Item']['type'] == 'file' &&
			$nc2Item['Item']['item_name'] == 'USER_ITEM_AVATAR'
		) {
			return 'img';
		}

		if ($nc2Item['Item']['type'] == 'select' &&
			$nc2Item['Item']['tag_name'] == 'timezone_offset_lang'
		) {
			return 'timezone';
		}

		if ($nc2Item['Item']['type'] == 'password' &&
			$nc2Item['Item']['tag_name'] == 'password'
		) {
			return $nc2Item['Item']['type'];
		}

		$validTypes = [
			'text',
			'radio',
			'checkbox',
			'select',
			'textarea',
			'email',
			'label'
		];
		if (!in_array($nc2Item['Item']['type'], $validTypes)) {
			return '';
		}

		return $nc2Item['Item']['type'];
	}

/**
 * Get nc3 UserAttribute id by nc2 tag_name and nc3 data_type_key
 *
 * @param array $nc2Item nc2 item data
 * @param string $dataTypeKey nc3 data_type_key
 * @return string Converted Nc2 type
 */
	private function __getNc3UserAttributeIdByTagNameAndDataTypeKey($nc2Item, $dataTypeKey) {
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

		$userAttributeId = null;
		$tagName = $nc2Item['Item']['tag_name'];
		if (!in_array($tagName, $defaultTagNames)) {
			return $userAttributeId;
		}

		$mappingTagToKey = [
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

		$query = array(
			'fields' => 'UserAttribute.id',
			'conditions' => [
				'UserAttribute.key' => $mappingTagToKey[$tagName],
				'UserAttributeSetting.data_type_key' => $dataTypeKey
			],
			'recursive' => 0
		);
		$userAttribute = $this->find('first', $query);
		if (!$userAttribute) {
			return $userAttributeId;
		}
		$userAttributeId = $userAttribute['UserAttribute']['id'];

		return $userAttributeId;
	}

/**
 * Get nc3 UserAttribute id by nc2 defaultvitem_name and nc3 data_type_key
 *
 * @param array $nc2Item nc2 item data
 * @param string $dataTypeKey nc3 data_type_key
 * @return string Converted Nc2 type
 */
	private function __getNc3UserAttributeIdByDefaultItemNameAndDataTypeKey($nc2Item, $dataTypeKey) {
		$userAttributeId = null;
		switch ($nc2Item['Item']['item_name']) {
			case 'USER_ITEM_AVATAR':
				$nc3UserAttributeKey = 'avatar';
				break;

			case 'USER_ITEM_MOBILE_EMAIL':
				$nc3UserAttributeKey = 'moblie_mail';
				break;

			case 'USER_ITEM_GENDER':
				$nc3UserAttributeKey = 'sex';
				break;

			case 'USER_ITEM_PROFILE':
				$nc3UserAttributeKey = 'profile';
				break;

			default:
				return $userAttributeId;

		}

		$query = array(
			'fields' => 'UserAttribute.id',
			'conditions' => [
				'UserAttribute.key' => $nc3UserAttributeKey,
				'UserAttributeSetting.data_type_key' => $dataTypeKey
			],
			'recursive' => 0
		);
		$userAttribute = $this->find('first', $query);
		if (!$userAttribute) {
			return $userAttributeId;
		}
		$userAttributeId = $userAttribute['UserAttribute']['id'];

		return $userAttributeId;
	}

/**
 * Get nc3 UserAttribute id by nc2 tag_name and nc3 data_type_key
 *
 * @param array $nc2Item nc2 item data
 * @param string $dataTypeKey nc3 data_type_key
 * @return string Converted Nc2 type
 */
	private function __getNc3UserAttributeIdByItemNameAndDataTypeKey($nc2Item, $dataTypeKey) {
		$userAttributeId = null;
		$query = array(
			'fields' => 'UserAttribute.id',
			'conditions' => [
				'UserAttribute.key' => $nc2Item['Item']['item_name'],
				'UserAttribute.language_id' => $this->__languageIdFromNc2,
				'UserAttributeSetting.data_type_key' => $dataTypeKey
			],
			'recursive' => 0
		);
		$userAttribute = $this->find('first', $query);
		if (!$userAttribute) {
			return $userAttributeId;
		}
		$userAttributeId = $userAttribute['UserAttribute']['id'];

		return $userAttributeId;
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

/**
 * Generate nc3 data
 *
 * @param array $nc2Item nc2 item data
 * @return array Nc3 data
 */
	private function __generateNc3Data($nc2Item) {
		/*

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
		data[UserAttributeChoice][1][1][id]:
		data[UserAttributeChoice][1][1][language_id]:1
		data[UserAttributeChoice][1][1][user_attribute_id]:
		data[UserAttributeChoice][1][1][key]:
		data[UserAttributeChoice][1][1][code]:
		data[UserAttributeChoice][1][1][weight]:1
		data[UserAttributeChoice][1][1][name]:
		data[UserAttributeChoice][2][1][id]:
		data[UserAttributeChoice][2][1][language_id]:1
		data[UserAttributeChoice][2][1][user_attribute_id]:
		data[UserAttributeChoice][2][1][key]:
		data[UserAttributeChoice][2][1][code]:
		data[UserAttributeChoice][2][1][weight]:2
		data[UserAttributeChoice][2][1][name]:
		data[UserAttributeChoice][3][1][id]:
		data[UserAttributeChoice][3][1][language_id]:1
		data[UserAttributeChoice][3][1][user_attribute_id]:
		data[UserAttributeChoice][3][1][key]:
		data[UserAttributeChoice][3][1][code]:
		data[UserAttributeChoice][3][1][weight]:3
		data[UserAttributeChoice][3][1][name]:
		data[UserAttributeChoice][1][2][id]:
		data[UserAttributeChoice][1][2][language_id]:2
		data[UserAttributeChoice][1][2][user_attribute_id]:
		data[UserAttributeChoice][1][2][key]:
		data[UserAttributeChoice][1][2][code]:
		data[UserAttributeChoice][1][2][weight]:1
		data[UserAttributeChoice][1][2][name]:
		data[UserAttributeChoice][2][2][id]:
		data[UserAttributeChoice][2][2][language_id]:2
		data[UserAttributeChoice][2][2][user_attribute_id]:
		data[UserAttributeChoice][2][2][key]:
		data[UserAttributeChoice][2][2][code]:
		data[UserAttributeChoice][2][2][weight]:2
		data[UserAttributeChoice][2][2][name]:
		data[UserAttributeChoice][3][2][id]:
		data[UserAttributeChoice][3][2][language_id]:2
		data[UserAttributeChoice][3][2][user_attribute_id]:
		data[UserAttributeChoice][3][2][key]:
		data[UserAttributeChoice][3][2][code]:
		data[UserAttributeChoice][3][2][weight]:3
		data[UserAttributeChoice][3][2][name]:
		$this->saveUserAttribute($data);


		'radio'
			'checkbox'
			'select'
		*/
	}
}
