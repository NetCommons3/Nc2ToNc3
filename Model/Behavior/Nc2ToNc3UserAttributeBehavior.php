<?php
/**
 * Nc2ToNc3UserAttributeBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3UserAttributeBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserAttributeBehavior
 *
 */
class Nc2ToNc3UserAttributeBehavior extends Nc2ToNc3UserAttributeBaseBehavior {

/**
 * Check migration target
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Item nc2 item data
 * @return bool True if data is migration target
 */
	public function isMigrationRow(Model $model, $nc2Item) {
		$tagName = $nc2Item['Nc2Item']['tag_name'];
		$logArgument = 'Nc2Item.id:' . $nc2Item['Nc2Item']['item_id'];
		$notMigrationTagNames = [
			'mobile_texthtml_mode',
			'mobile_imgdsp_size',
			'userinf_view_main_room',
			'userinf_view_main_monthly',
			'userinf_view_main_modulesinfo'
		];
		if (in_array($tagName, $notMigrationTagNames)) {
			$this->_writeMigrationLog(__d('nc2_to_nc3', '%s is not migration.', $logArgument));
			return false;
		}

		$dataTypeKey = $this->__convertNc2Type($nc2Item);
		if (!$dataTypeKey) {
			$this->_writeMigrationLog(__d('nc2_to_nc3', '%s is not migration.', $logArgument));
			return;
		}

		return true;
	}

/**
 * Map existing id
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Item nc2 item data
 * @return void
 */
	public function mapExistingId(Model $model, $nc2Item) {
		$dataTypeKey = $this->__convertNc2Type($nc2Item);
		$logArgument = 'Nc2Item.id:' . $nc2Item['Nc2Item']['item_id'];

		$nc3Id = $this->__getNc3UserAttributeIdByTagNameAndDataTypeKey($nc2Item, $dataTypeKey);
		if ($nc3Id) {
			$nc2Id = $nc2Item['Nc2Item']['item_id'];
			$model->mappingId[$nc2Id] = $nc3Id;

			$this->_writeMigrationLog(__d('nc2_to_nc3', '%s is not migration.', $logArgument));
			return;
		}

		$nc3Id = $this->__getNc3UserAttributeIdByDefaultItemNameAndDataTypeKey($nc2Item, $dataTypeKey);
		if ($nc3Id) {
			$nc2Id = $nc2Item['Nc2Item']['item_id'];
			$model->mappingId[$nc2Id] = $nc3Id;

			$this->_writeMigrationLog(__d('nc2_to_nc3', '%s is not migration.', $logArgument));
			return;
		}

		$nc3Id = $this->__getNc3UserAttributeIdByItemNameAndDataTypeKey($nc2Item, $dataTypeKey);
		if ($nc3Id) {
			$nc2Id = $nc2Item['Nc2Item']['item_id'];
			$model->mappingId[$nc2Id] = $nc3Id;

			$this->_writeMigrationLog(__d('nc2_to_nc3', '%s is not migration.', $logArgument));
			return;
		}
	}

/**
 * Check mergence target
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Item nc2 item data
 * @return bool True if data is mergence target
 */
	public function isChoiceMergenceRow(Model $model, $nc2Item) {
		$choiceTypes = [
			'radio',
			'checkbox',
			'select'
		];
		if (!in_array($nc2Item['Nc2Item']['type'], $choiceTypes)) {
			return false;
		}

		$notMergenceTagNames = [
			'lang_dirname_lang',
			'timezone_offset_lang',
			'role_authority_name',
			'active_flag_lang',
		];
		if (in_array($nc2Item['Nc2Item']['tag_name'], $notMergenceTagNames)) {
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
		if ($nc2Item['Nc2Item']['type'] == 'mobile_email') {
			return 'email';
		}

		if ($nc2Item['Nc2Item']['type'] == 'file' &&
			$nc2Item['Nc2Item']['item_name'] == 'USER_ITEM_AVATAR'
		) {
			return 'img';
		}

		if ($nc2Item['Nc2Item']['type'] == 'select' &&
			$nc2Item['Nc2Item']['tag_name'] == 'timezone_offset_lang'
		) {
			return 'timezone';
		}

		if ($nc2Item['Nc2Item']['type'] == 'password' &&
			$nc2Item['Nc2Item']['tag_name'] == 'password'
		) {
			return $nc2Item['Nc2Item']['type'];
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
		if (!in_array($nc2Item['Nc2Item']['type'], $validTypes)) {
			return '';
		}

		return $nc2Item['Nc2Item']['type'];
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
		$tagName = $nc2Item['Nc2Item']['tag_name'];
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

		$UserAttribute = $this->_getNc3Model('UserAttribute.UserAttribute');
		$query = [
			'fields' => 'UserAttribute.id',
			'conditions' => [
				'UserAttribute.key' => $mappingTagToKey[$tagName],
				'UserAttributeSetting.data_type_key' => $dataTypeKey
			],
			'recursive' => 0
		];
		$userAttribute = $UserAttribute->find('first', $query);
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
		$mappingItemNameToKey = [
			'USER_ITEM_AVATAR' => 'avatar',
			'USER_ITEM_MOBILE_EMAIL' => 'moblie_mail',
			'USER_ITEM_GENDER' => 'sex',
			'USER_ITEM_PROFILE' => 'profile',
		];
		$userAttributeId = null;
		$itemName = $nc2Item['Nc2Item']['item_name'];
		if (!isset($mappingItemNameToKey[$itemName])) {
			return $userAttributeId;
		}

		$nc3UserAttributeKey = $mappingItemNameToKey[$itemName];
		$UserAttribute = $this->_getNc3Model('UserAttribute.UserAttribute');
		$query = [
			'fields' => 'UserAttribute.id',
			'conditions' => [
				'UserAttribute.key' => $nc3UserAttributeKey,
				'UserAttributeSetting.data_type_key' => $dataTypeKey
			],
			'recursive' => 0
		];
		$userAttribute = $UserAttribute->find('first', $query);
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
		$UserAttribute = $this->_getNc3Model('UserAttribute.UserAttribute');
		$query = [
			'fields' => 'UserAttribute.id',
			'conditions' => [
				'UserAttribute.name' => $nc2Item['Nc2Item']['item_name'],
				'UserAttribute.language_id' => $this->__languageIdFromNc2,
				'UserAttributeSetting.data_type_key' => $dataTypeKey
			],
			'recursive' => 0
		];
		$userAttribute = $UserAttribute->find('first', $query);
		if (!$userAttribute) {
			return $userAttributeId;
		}
		$userAttributeId = $userAttribute['UserAttribute']['id'];

		return $userAttributeId;
	}

}
