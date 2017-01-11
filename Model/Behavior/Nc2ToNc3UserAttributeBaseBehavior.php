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
 * Get Nc2 autoregist_use_items from config
 *
 * @param Model $model Model using this behavior
 * @return string Nc2 autoregist_use_items
 */
	public function getNc2AutoregistUseItems(Model $model) {
		$this->_getNc2AutoregistUseItems();
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

		$Language = $this->_getNc3Model('M17n.Language');
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
 * Get Nc2 autoregist_use_items from config
 *
 * @return string Nc2 autoregist_use_items
 */
	protected function _getNc2AutoregistUseItems() {
		if (!isset($this->__nc2AutoregistUseItems)) {
			$Nc2Config = $this->getNc2Model('config');
			$autoregistUseItems = $Nc2Config->findByConfName('utoregist_use_items', 'conf_value', null, -1);
			$autoregistUseItems = explode('|', $autoregistUseItems);
			if (!end($autoregistUseItems)) {
				array_pop($autoregistUseItems);
			}
			$this->__nc2AutoregistUseItems = [];
			foreach ($autoregistUseItems as $autoregistUseItem) {
				list($itemId, $isRequired) = explode(':', $autoregistUseItem);
				$this->__nc2AutoregistUseItems[$itemId] = $isRequired;
			}
		}

		return $this->__nc2AutoregistUseItems;
	}

/**
 * Set Nc2 item constant.
 *
 * @return void
 */
	private function __setNc2ItemConstants() {
		$Language = $this->_getNc3Model('M17n.Language');
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
		$pathConfig = $this->_getPathConfig();
		if (!$pathConfig['items_ini_path']) {
			return;
		}

		// TODOーitems.iniから定数を取得しマージ
		$nc2ItemConstants = $pathConfig['items_ini_path'];
		array_merge_recursive($this->__nc2ItemConstants, $nc2ItemConstants);
	}
}
