<?php
/**
 * Nc2ToNc3BaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('ModelBehavior', 'Model');
App::uses('Nc2ToNc3', 'Nc2ToNc3.Model');
App::uses('Nc2ToNc3DividedBaseLanguageBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('Nc2ToNc3DividedBaseConvertBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3MigrationBehavior
 *
 */
class Nc2ToNc3BaseBehavior extends ModelBehavior {

/**
 * Setup this behavior with the specified configuration settings.
 *
 * @param Model $model Model using this behavior
 * @param array $config Configuration settings for $model
 * @return void
 */
	public function setup(Model $model, $config = array()) {
		// Nc2ToNc3BaseBehavior::_writeMigrationLogでログ出力している
		// CakeLog::writeでファイルとコンソールに出力していた。
		// Consoleに出力すると<tag></tag>で囲われ見辛い。
		// @see
		// https://github.com/cakephp/cakephp/blob/2.9.4/lib/Cake/Console/ConsoleOutput.php#L230-L241
		// CakeLog::infoをよびだし、debug.logとNc2ToNc3.logの両方出力するようにした。
		CakeLog::config(
			'Nc2ToNc3File',
			[
				'engine' => 'FileLog',
				'types' => ['info'],
				'scopes' => ['Nc2ToNc3'],
				'file' => 'Nc2ToNc3.log',
			]
		);

		// PHPMD ExcessiveClassComplexity になるため
		// Nc2ToNc3DividedBaseLanguageBehavior
		// Nc2ToNc3DividedBaseConvertBehavior
		// へ分割
		//
		// 色々試みたが、ここでClassRegistryに登録し、メソッドが呼び出された際に使用する（メソッドはラッパーになる）方法が良いと思った。
		if (!ClassRegistry::isKeySet('Nc2ToNc3DividedBaseLanguageBehavior')) {
			ClassRegistry::addObject('Nc2ToNc3DividedBaseLanguageBehavior', new Nc2ToNc3DividedBaseLanguageBehavior());
			ClassRegistry::addObject('Nc2ToNc3DividedBaseConvertBehavior', new Nc2ToNc3DividedBaseConvertBehavior());
		}

		// 結果ややこしくなるし、共通処理を追加する際に、このクラスにも定義しないといけないので、効率悪い。
		// 分割したクラスを呼び出す際にも、Modelの引数を考えないといけないし面倒。
		// SuppressWarnings(PHPMD ExcessiveClassComplexity) で回避した方が良いと思った。
	}

/**
 * Write migration log.
 *
 * @param Model $model Model using this behavior.
 * @param string $message Migration message.
 * @return void
 */
	public function writeMigrationLog(Model $model, $message) {
		$debugString = '';
		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
		if (isset($backtraces[4]) &&
			isset($backtraces[4]['line']) &&
			isset($backtraces[4]['class']) &&
			$backtraces[4]['function'] == 'writeMigrationLog'
		) {
			$debugString = $backtraces[4]['class'] . ' on line ' . $backtraces[4]['line'];
		}

		$this->_writeMigrationLog($message, $debugString);
	}

/**
 * Get Nc2 Model.
 *
 * @param Model $model Model using this behavior.
 * @param string $tableName Nc2 table name.
 * @return Model Nc2 model
 */
	public function getNc2Model(Model $model, $tableName) {
		return $this->_getNc2Model($tableName);
	}

/**
 * Get languageId from Nc2.
 *
 * @param Model $model Model using this behavior.
 * @return string LanguageId from Nc2.
 */
	public function getLanguageIdFromNc2(Model $model) {
		return $this->_getLanguageIdFromNc2();
	}

/**
 * Convert nc2 date.
 *
 * @param Model $model Model using this behavior.
 * @param string $date Nc2 date.
 * @return Model converted date.
 */
	public function convertDate(Model $model, $date) {
		return $this->_convertDate($date);
	}

/**
 * Convert nc2 lang_dirname.
 *
 * @param Model $model Model using this behavior.
 * @param string $langDirName nc2 lang_dirname.
 * @return string converted nc2 lang_dirname.
 */
	public function convertLanguage(Model $model, $langDirName) {
		return $this->_convertLanguage($langDirName);
	}

/**
 * Save Nc2ToNc3Map
 *
 * @param Model $model Model using this behavior.
 * @param string $modelName Model name
 * @param array $idMap Nc2ToNc3Map.nc3_id with Nc2ToNc3Map.nc2_id as key.
 * @return void
 */
	public function saveMap(Model $model, $modelName, $idMap) {
		$this->_saveMap($modelName, $idMap);
	}

/**
 * Get map.
 *
 * 継承したクラスの_getMapメソッドを呼び出す
 *
 * @param Model $model Model using this behavior.
 * @param array|string $nc2Ids Nc2 id.
 * @return string Id map.
 */
	public function getMap(Model $model, $nc2Ids = null) {
		return $this->_getMap($nc2Ids);
	}

/**
 * Change nc3 current language data
 *
 * @param Model $model Model using this behavior.
 * @param string $langDirName nc2 lang_dirname.
 * @return void
 */
	public function changeNc3CurrentLanguage(Model $model, $langDirName = null) {
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
 * Convert nc2 display_days.
 *
 * @param Model $model Model using this behavior.
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	public function convertDisplayDays(Model $model, $displayDays) {
		return $this->_convertDisplayDays($displayDays);
	}

/**
 * Convert nc2 choice value
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted nc3 value.
 */
	public function convertChoiceValue(Model $model, $nc2Value, $nc3Choices) {
		return $this->_convertChoiceValue($nc2Value, $nc3Choices);
	}

/**
 * Write migration log.
 *
 * @param string $message Migration message.
 * @param string $debugString Debug string.
 * @return void
 */
	protected function _writeMigrationLog($message, $debugString = '') {
		if ($debugString) {
			CakeLog::info($message . ' : ' . $debugString, ['Nc2ToNc3']);
			return;
		}

		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		if (isset($backtraces[0]) &&
			isset($backtraces[0]['line']) &&
			isset($backtraces[1]['class']) &&
			$backtraces[0]['function'] == '_writeMigrationLog'
		) {
			$message = $message . ' : ' . $backtraces[1]['class'] . ' on line ' . $backtraces[0]['line'];
		}

		CakeLog::info($message, ['Nc2ToNc3']);
	}

/**
 * Get Nc2 Model.
 *
 * @param string $tableName Nc2 table name.
 * @return Model Nc2 model.
 */
	protected function _getNc2Model($tableName) {
		// クラス自体は存在しない。
		// Nc2ToNc3AppModelのインスタンスを作成し返す。
		// Nc2ToNc3AppModelはNetCommonsAppModelを継承しない。
		$Molde = ClassRegistry::init([
			'class' => 'Nc2ToNc3.Nc2' . $tableName,
			'table' => $tableName,
			'alias' => 'Nc2' . Inflector::classify($tableName),
			'ds' => Nc2ToNc3::CONNECTION_NAME
		]);

		return $Molde;
	}

/**
 * Get languageId from Nc2.
 *
 * @return string LanguageId from Nc2.
 */
	protected function _getLanguageIdFromNc2() {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseLanguageBehavior');
		return $Nc2ToNc3DividedBase->getLanguageIdFromNc2();
	}

/**
 * Convert nc3 date.
 *
 * @param string $date Nc2 date.
 * @return string converted date.
 */
	protected function _convertDate($date) {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseConvertBehavior');
		return $Nc2ToNc3DividedBase->convertDate(null, $date);
	}

/**
 * Convert nc2 lang_dirname.
 *
 * @param string $langDirName nc2 lang_dirname.
 * @return string converted nc2 lang_dirname.
 */
	protected function _convertLanguage($langDirName) {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseLanguageBehavior');
		return $Nc2ToNc3DividedBase->convertLanguage(null, $langDirName);
	}

/**
 * Save Nc2ToNc3Map
 *
 * @param string $modelName Model name
 * @param array $idMap Nc2ToNc3Map.nc3_id with Nc2ToNc3Map.nc2_id as key.
 * @return array Nc2ToNc3Map data.
 */
	protected function _saveMap($modelName, $idMap) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$data['Nc2ToNc3Map'] = [
			'model_name' => $modelName,
			'nc2_id' => key($idMap),
			'nc3_id' => current($idMap),
		];

		return $Nc2ToNc3Map->saveMap($data);
	}

/**
 * Change nc3 current language data
 *
 * @param string $langDirName nc2 lang_dirname.
 * @return void
 */
	protected function _changeNc3CurrentLanguage($langDirName = null) {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseLanguageBehavior');
		return $Nc2ToNc3DividedBase->changeNc3CurrentLanguage(null, $langDirName);
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	protected function _restoreNc3CurrentLanguage() {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseLanguageBehavior');
		return $Nc2ToNc3DividedBase->restoreNc3CurrentLanguage();
	}

/**
 * Convert nc2 display_days.
 *
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	protected function _convertDisplayDays($displayDays) {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseConvertBehavior');
		return $Nc2ToNc3DividedBase->convertDisplayDays(null, $displayDays);
	}

/**
 * Convert nc2 choice value.
 *
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted nc3 value.
 */
	protected function _convertChoiceValue($nc2Value, $nc3Choices) {
		$Nc2ToNc3DividedBase = ClassRegistry::getObject('Nc2ToNc3DividedBaseConvertBehavior');
		return $Nc2ToNc3DividedBase->convertChoiceValue(null, $nc2Value, $nc3Choices);
	}

}
