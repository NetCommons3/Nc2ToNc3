<?php
/**
 * Nc2ToNc3UserAttributeBaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserAttributeBaseBehavior
 *
 */
class Nc2ToNc3UserAttributeBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Corresponding id to nc2 and nc3.
 *
 * @var array
 */
	private $__correspondingIds = null;

/**
 * Language id from Nc2.
 *
 * @var array
 */
	private $__languageIdFromNc2 = null;

/**
 * Nc2 item constant.
 *
 * @var array
 */
	private $__nc2ItemConstants = null;

/**
 * Nc2 item description.
 *
 * @var array
 */
	private $__nc2ItemDescriptions = null;

/**
 * Nc2 autoregist_use_items
 *
 * @var array
 */
	private $__nc2AutoregistUseItems = null;

/**
 * Nc3 UserAttributeSetting weight
 *
 * @var int
 */
	private $__userAttributeSettingWeight = null;

/**
 * Set corresponding id
 *
 * @param Model $model Model using this behavior
 * @param string $nc2ItemId Nc2 item id
 * @param string $nc3UserAttributeId Nc3 UserAttribute id
 * @return void
 */
	public function setCorrespondingId(Model $model, $nc2ItemId, $nc3UserAttributeId) {
		$this->_setCorrespondingId($nc2ItemId, $nc3UserAttributeId);
	}

/**
 * Get corresponding id
 *
 * @param Model $model Model using this behavior
 * @param string $nc2ItemId Nc2 item id
 * @return array corresponding id
 */
	public function getCorrespondingId(Model $model, $nc2ItemId) {
		return $this->_getCorrespondingId($nc2ItemId);
	}

/**
 * Get corresponding id array
 *
 * @param Model $model Model using this behavior
 * @return array corresponding id array
 */
	public function getCorrespondingIds(Model $model) {
		return $this->_getCorrespondingIds();
	}
/**
 * Get languageId from Nc2
 *
 * @param Model $model Model using this behavior
 * @return string LanguageId from Nc2
 */
	public function getLanguageIdFromNc2(Model $model) {
		return $this->_getLanguageIdFromNc2();
	}

/**
 * Get Nc2 item value by constant.
 *
 * @param Model $model Model using this behavior
 * @param string $constant Nc2 item constant
 * @param string $languageId Nc2 language id
 * @return string Nc2 item value
 */
	public function getNc2ItemValueByConstant(Model $model, $constant, $languageId) {
		return $this->_getNc2ItemValueByConstant($constant, $languageId);
	}

/**
 * Get Nc2 item description by id.
 *
 * @param Model $model Model using this behavior
 * @param string $itemId Nc2 item id
 * @return string Nc2 item description
 */
	public function getNc2ItemDescriptionById(Model $model, $itemId) {
		return $this->_getNc2ItemDescriptionById($itemId);
	}

/**
 * Check Nc2 autoregist_use_items from config
 *
 * @param Model $model Model using this behavior
 * @param string $itemId Nc2 item id
 * @return bool True if data is nc2 autoregist_use_items
 */
	public function isNc2AutoregistUseItem(Model $model, $itemId) {
		return $this->_isNc2AutoregistUseItem($itemId);
	}

/**
 * Check require Nc2 autoregist_use_items from config
 *
 * @param Model $model Model using this behavior
 * @param string $itemId Nc2 item id
 * @return bool True if data require as nc2 autoregist_use_items
 */
	public function isNc2AutoregistUseItemRequire(Model $model, $itemId) {
		return $this->_isNc2AutoregistUseItemRequire($itemId);
	}

/**
 * Get Nc3 UserAttributeSetting row.
 *
 * @param Model $model Model using this behavior
 * @return string Nc3 UserAttributeSetting row
 */
	public function getUserAttributeSettingRow(Model $model) {
		return $this->_getUserAttributeSettingRow();
	}

/**
 * Get Nc3 UserAttributeSetting col.
 *
 * @param Model $model Model using this behavior
 * @return string Nc3 UserAttributeSetting col
 */
	public function getUserAttributeSettingCol(Model $model) {
		return $this->_getUserAttributeSettingCol();
	}

/**
 * Get Nc3 UserAttributeSetting weight.
 *
 * @param Model $model Model using this behavior
 * @return int Nc3 UserAttributeSetting weight
 */
	public function getUserAttributeSettingWeight(Model $model) {
		return $this->_getUserAttributeSettingWeight();
	}

/**
 * Increment Nc3 UserAttributeSetting weight.
 *
 * @param Model $model Model using this behavior
 * @return void
 */
	public function incrementUserAttributeSettingWeight(Model $model) {
		return $this->_incrementUserAttributeSettingWeight();
	}

/**
 * Set corresponding id
 *
 * @param string $nc2ItemId Nc2 item id
 * @param string $nc3UserAttributeId Nc3 UserAttribute id
 * @return void
 */
	protected function _setCorrespondingId($nc2ItemId, $nc3UserAttributeId) {
		$this->__correspondingIds[$nc2ItemId] = $nc3UserAttributeId;
	}

/**
 * Get corresponding id
 *
 * @param string $nc2ItemId Nc2 item id
 * @return array corresponding id
 */
	protected function _getCorrespondingId($nc2ItemId) {
		return Hash::get($this->__correspondingIds, [$nc2ItemId]);
	}

/**
 * Get corresponding id array
 *
 * @return array corresponding id array
 */
	protected function _getCorrespondingIds() {
		return $this->__correspondingIds;
	}

/**
 * Get languageId from Nc2
 *
 * @return string LanguageId from Nc2
 */
	protected function _getLanguageIdFromNc2() {
		if (isset($this->__languageIdFromNc2)) {
			return $this->__languageIdFromNc2;
		}

		$Nc2Config = $this->_getNc2Model('config');
		$configData = $Nc2Config->findByConfName('language', 'conf_value', null, -1);

		$language = $configData['Nc2Config']['conf_value'];
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

		return $this->__languageIdFromNc2;
	}

/**
 * Get Nc2 item value by constant.
 *
 * @param string $constant Nc2 item constant
 * @param string $languageId Nc2 language id
 * @return string Nc2 item value
 */
	protected function _getNc2ItemValueByConstant($constant, $languageId) {
		if (!isset($this->__nc2ItemConstants)) {
			$this->__setNc2ItemConstants();
		}

		return Hash::get($this->__nc2ItemConstants, [$constant, $languageId], $constant);
	}

/**
 * Get Nc2 item description by id.
 *
 * @param string $itemId Nc2 item id
 * @return string Nc2 item description
 */
	protected function _getNc2ItemDescriptionById($itemId) {
		if (!isset($this->__nc2ItemDescriptions)) {
			$Nc2ItemsDesc = $this->_getNc2Model('items_desc');
			$query = [
				'fields' => [
					'Nc2ItemsDesc.item_id',
					'Nc2ItemsDesc.description'
				],
				'recursive' => -1
			];
			$this->__nc2ItemDescriptions = $Nc2ItemsDesc->find('list', $query);
		}

		return Hash::get($this->__nc2ItemDescriptions, [$itemId], '');
	}

/**
 * Check Nc2 autoregist_use_items from config
 *
 * @param string $itemId Nc2 item id
 * @return bool True if data is nc2 autoregist_use_items
 */
	protected function _isNc2AutoregistUseItem($itemId) {
		if (!isset($this->__nc2AutoregistUseItems)) {
			$this->__setNc2AutoregistUseItems();
		}

		return isset($this->_isNc2AutoregistUseItems[$itemId]);
	}

/**
 * Check require Nc2 autoregist_use_items from config
 *
 * @param string $itemId Nc2 item id
 * @return bool True if data require as nc2 autoregist_use_items
 */
	protected function _isNc2AutoregistUseItemRequire($itemId) {
		if (!isset($this->__nc2AutoregistUseItems)) {
			$this->__setNc2AutoregistUseItems();
		}

		$isRequire = (
			isset($this->_isNc2AutoregistUseItems[$itemId]) &&
			$this->_isNc2AutoregistUseItems[$itemId] == '1'
		);

		return $isRequire;
	}

/**
 * Get Nc3 UserAttributeSetting row.
 *
 * @return string Nc3 UserAttributeSetting row
 */
	protected function _getUserAttributeSettingRow() {
		// 1行目
		return '1';
	}

/**
 * Get Nc3 UserAttributeSetting col.
 *
 * @return string Nc3 UserAttributeSetting col
 */
	protected function _getUserAttributeSettingCol() {
		// 2列目
		return '2';
	}

/**
 * Get Nc3 UserAttributeSetting weight.
 *
 * @return int Nc3 UserAttributeSetting weight
 */
	protected function _getUserAttributeSettingWeight() {
		if (!isset($this->__userAttributeSettingWeight)) {
			$this->__setUserAttributeSettingWeight();
		}

		return $this->__userAttributeSettingWeight;
	}

/**
 * Increment Nc3 UserAttributeSetting weight.
 *
 * @return void
 */
	protected function _incrementUserAttributeSettingWeight() {
		if (!isset($this->__userAttributeSettingWeight)) {
			$this->__setUserAttributeSettingWeight();
		}

		$this->__userAttributeSettingWeight++;
	}

/**
 * Set Nc2 item constant.
 *
 * @return void
 */
	private function __setNc2ItemConstants() {
		$Language = ClassRegistry::init('M17n.Language');
		$query = [
			'fields' => [
				'Language.code',
				'Language.id'
			],
			'recursive' => -1
		];
		$language = $Language->find('list', $query);

		$this->__nc2ItemConstants = [
			'USER_ITEM_LOGIN' => [
				$language['ja'] => 'ログインID',
				$language['en'] => 'ID',
			],
			'USER_ITEM_PASSWORD' => [
				$language['ja'] => 'パスワード',
				$language['en'] => 'Password',
			],
			'USER_ITEM_USER_NAME' => [
				$language['ja'] => '会員氏名',
				$language['en'] => 'Name',
			],
			'USER_ITEM_HANDLE' => [
				$language['ja'] => 'ハンドル',
				$language['en'] => 'Handle',
			],
			'USER_ITEM_LANG_DIRNAME' => [
				$language['ja'] => '言語',
				$language['en'] => 'Language',
			],
			'USER_ITEM_TIMEZONE_OFFSET' => [
				$language['ja'] => 'タイムゾーン',
				$language['en'] => 'TimeZone',
			],
			'USER_ITEM_AVATAR' => [
				$language['ja'] => 'アバター',
				$language['en'] => 'Avatar',
			],
			'USER_ITEM_PROFILE' => [
				$language['ja'] => 'プロフィール',
				$language['en'] => 'Profile',
			],
			'USER_ITEM_EMAIL' => [
				$language['ja'] => 'eメール',
				$language['en'] => 'E-mail',
			],
			'USER_ITEM_MOBILE_EMAIL' => [
				$language['ja'] => '携帯メール',
				$language['en'] => 'Mobile mail',
			],
			'USER_ITEM_GENDER' => [
				$language['ja'] => '性別',
				$language['en'] => 'Sex',
			],
			'USER_ITEM_COUNTRY_CODE' => [
				$language['ja'] => '国名',
				$language['en'] => 'Nationality',
			],
			'USER_ITEM_ADDRESS' => [
				$language['ja'] => '住所',
				$language['en'] => 'Location',
			],
			'USER_ITEM_FAVORITE' => [
				$language['ja'] => '趣味',
				$language['en'] => 'Interest',
			],
			'USER_ITEM_GENDER_MAN' => [
				$language['ja'] => '男',
				$language['en'] => 'Interest',
			],
			'USER_ITEM_GENDER_WOMAN' => [
				$language['ja'] => '女',
				$language['en'] => 'Female',
			],
		];

		$this->__mergeNc2ItemConstantsItemFile();
	}

/**
 * Merge Nc2 item constant.
 *
 * @return void
 */
	private function __mergeNc2ItemConstantsItemFile() {
		$Nc2ToNc3 = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3');
		$itemsIniPath = Hash::get($Nc2ToNc3->data, ['Nc2ToNc3', 'items_ini_path']);
		if (!$itemsIniPath) {
			return;
		}

		// TODOーitems.iniから定数を取得しマージ
		$nc2ItemConstants = $itemsIniPath;
		array_merge_recursive($this->__nc2ItemConstants, $nc2ItemConstants);
	}

/**
 * Set Nc2 autoregist_use_items from config
 *
 * @return void
 */
	private function __setNc2AutoregistUseItems() {
		$Nc2Config = $this->_getNc2Model('config');
		$autoregistUseItems = $Nc2Config->findByConfName('autoregist_use_items', 'conf_value', null, -1);
		$autoregistUseItems = explode('|', $autoregistUseItems['Nc2Config']['conf_value']);
		if (!end($autoregistUseItems)) {
			array_pop($autoregistUseItems);
		}
		$this->__nc2AutoregistUseItems = [];
		foreach ($autoregistUseItems as $autoregistUseItem) {
			list($itemId, $isRequired) = explode(':', $autoregistUseItem);
			$this->__nc2AutoregistUseItems[$itemId] = $isRequired;
		}
	}

/**
 * Set Nc3 UserAttributeSetting weight.
 *
 * @return void
 */
	private function __setUserAttributeSettingWeight() {
		$UserAttribute = ClassRegistry::init('UserAttribute.UserAttribute');
		$this->__userAttributeSettingWeight = $UserAttribute->UserAttributeSetting->getMaxWeight(
			$this->_getUserAttributeSettingRow(),
			$this->_getUserAttributeSettingCol()
		);
		$this->_incrementUserAttributeSettingWeight();
	}

}
