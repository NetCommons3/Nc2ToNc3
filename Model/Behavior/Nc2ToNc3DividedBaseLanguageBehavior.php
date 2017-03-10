<?php
/**
 * Nc2ToNc3DividedBaseLanguageBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3DividedBaseLanguageBehavior
 *
 */
class Nc2ToNc3DividedBaseLanguageBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Language id from Nc2.
 *
 * @var array
 */
	private $__languageIdFromNc2 = null;

/**
 * Language list.
 *
 * @var array
 */
	private $__languageList = null;

/**
 * Nc3Language data.
 *
 * @var array
 */
	private $__nc3CurrentLanguage = null;

/**
 * Get languageId from Nc2.
 *
 * @param Model $model Model using this behavior.
 * @return string LanguageId from Nc2.
 */
	public function getLanguageIdFromNc2(Model $model = null) {
		return $this->_getLanguageIdFromNc2();
	}

/**
 * Convert nc2 lang_dirname.
 *
 * @param Model $model Model using this behavior.
 * @param string $langDirName nc2 lang_dirname.
 * @return string converted nc2 lang_dirname.
 */
	public function convertLanguage(Model $model = null, $langDirName) {
		return $this->_convertLanguage($langDirName);
	}

/**
 * Change nc3 current language data
 *
 * @param Model $model Model using this behavior.
 * @param string $langDirName nc2 lang_dirname.
 * @return void
 */
	public function changeNc3CurrentLanguage(Model $model = null, $langDirName = null) {
		$this->_changeNc3CurrentLanguage($langDirName);
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	public function restoreNc3CurrentLanguage() {
		$this->_restoreNc3CurrentLanguage();
	}

/**
 * Get languageId from Nc2.
 *
 * @return string LanguageId from Nc2.
 */
	protected function _getLanguageIdFromNc2() {
		// Model毎にInstanceが作成されるため、Model毎にNc2Configから読み込まれる
		// 今のところ、UserAttributeとUserだけなので、Propertyで保持するが、
		// 増えてきたらstatic等でNc2Configから読み込まないよう変更する
		if (isset($this->__languageIdFromNc2)) {
			return $this->__languageIdFromNc2;
		}

		/* @var $Nc2Config AppModel */
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

		/* @var $Language Language */
		$Language = ClassRegistry::init('M17n.Language');
		$language = $Language->findByCode($code, 'id', null, -1);
		$this->__languageIdFromNc2 = $language['Language']['id'];

		return $this->__languageIdFromNc2;
	}

/**
 * Convert nc2 lang_dirname.
 *
 * @param string $langDirName nc2 lang_dirname.
 * @return string converted nc2 lang_dirname.
 */
	protected function _convertLanguage($langDirName) {
		if (!$langDirName) {
			return null;
		}

		// Model毎にInstanceが作成されるため、Model毎にNc3Languageから読み込まれる
		// 今のところ、RoomとPageだけなので、Propertyで保持するが、
		// 増えてきたらstatic等でNc3Languageから読み込まないよう変更する
		// Nc2ToNc3LabuageというModelクラス作った方が良いかも。
		if (!isset($this->__languageList)) {
			/* @var $Language Language */
			$Language = ClassRegistry::init('M17n.Language');
			$query = [
				'fields' => [
					'Language.code',
					'Language.id'
				],
				'conditions' => [
					'is_active' => true
				],
				'recursive' => -1
			];
			$this->__languageList = $Language->find('list', $query);
		}

		$map = [
			'japanese' => 'ja',
			'english' => 'en',
			'chinese' => 'zh'
		];
		$code = $map[$langDirName];

		if (isset($this->__languageList[$code])) {
			return $this->__languageList[$code];
		}

		return null;
	}

/**
 * Change nc3 current language data
 *
 * @param string $langDirName nc2 lang_dirname.
 * @return void
 */
	protected function _changeNc3CurrentLanguage($langDirName = null) {
		$nc3LanguageId = null;
		if ($langDirName) {
			$nc3LanguageId = $this->_convertLanguage($langDirName);
		}
		if (!$nc3LanguageId) {
			$nc3LanguageId = $this->_getLanguageIdFromNc2();
		}

		/* @var $Language Language */
		$Language = ClassRegistry::init('M17n.Language');

		if (Current::read('Language.id') != $nc3LanguageId) {
			$this->__nc3CurrentLanguage = Current::read('Language');
			$language = $Language->findById($nc3LanguageId, null, null, -1);
			Current::write('Language', $language['Language']);
		}
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	protected function _restoreNc3CurrentLanguage() {
		if (isset($this->__nc3CurrentLanguage)) {
			Current::write('Language', $this->__nc3CurrentLanguage);
			unset($this->__nc3CurrentLanguage);
		}
	}

}
