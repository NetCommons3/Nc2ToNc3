<?php
/**
 * Nc2ToNc3UserAttributeItemConstantBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserAttributeItemConstantBehavior
 *
 */
class Nc2ToNc3UserAttributeNc2ItemConstantBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Nc2 item constant.
 *
 * @var array
 */
	private $__nc2ItemConstants = null;

/**
 * Setup this behavior with the specified configuration settings.
 *
 * @param Model $model Model using this behavior
 * @param array $config Configuration settings for $model
 * @return void
 */
	public function setup(Model $model, $config = array()) {
		$this->__setNc2ItemConstants();
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
 * Get Nc2 item constant.
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

/**
 * Get Nc2 item constant.
 *
 * @param Model $model Model using this behavior
 * @return void
 */
	public function getNc2ItemConstants(Model $model) {
		return $this->__nc2ItemConstants;
	}

}
