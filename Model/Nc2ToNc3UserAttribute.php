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

		$keyList = $this->__findDefaultKeyList();
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

		return true;
	}

/**
 * Save UserAttribue from Nc2
 *
 * @return bool True on success
 */
	public function saveUserAttributeFromNc2() {
		$keyList = $this->__findDefaultKeyList();

		$Nc2Item = $this->getNc2Model('items');
		var_dump($Nc2Item->find('first'));
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
	private function __findDefaultKeyList() {
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
				'UserAttribute.key' => $defaultKeys
			],
			'recursive' => -1
		];
		$keyList = $this->find('list', $query);

		return $keyList;
	}
}
