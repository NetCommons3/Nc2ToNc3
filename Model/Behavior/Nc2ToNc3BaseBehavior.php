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
App::uses('Nc2ToNc3BaseLanguage', 'Nc2ToNc3.Model/Behavior');
//App::uses('Nc2ToNc3', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3MigrationBehavior
 *
 */
class Nc2ToNc3BaseBehavior extends ModelBehavior {

/**
 * Magic method
 *
 * @param string $method Name of method to call.
 * @param array $params Parameters for the method.
 * @return mixed Whatever is returned by called method
 */
	public function __call($method, $params) {
		if ($method == '_getMap') {
			var_dump(get_class($this));exit;
		}
		$Modle = array_shift($params);	// 第１引数のModelを除去

		if ($method == '_getMap') {
		}

		var_dump($method);
	}

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
		// このsetupでロードし、呼び出し側の修正をしないようにしたい。
		//   → publicメソッドは、Model::__callから、BehaviorCollection::dispatchMethodで呼び出しくれるが、
		//     Behaviorから、第1引数になるModelを指定しないで呼び出すことができない（Behavior間のつながりがないので呼び出せない。）
		//   → なので、protectedメソッドを定義して、そこから、対象のメソッドを呼び出すようにする。
		//   → Trait機能使いたいが、PHP5.4からの機能。CakePHP(~2.7)がPHP5.3でも動作するらしいので使わない。
		//     @see https://book.cakephp.org/2.0/ja/appendices/2-7-migration-guide.html
		//   → BehaviorCollection::load から Behavior::setupが呼び出されるため、Nc2ToNc3BaseBehaviorを継承していると無限ループになる
		//      → ロードされていない場合の判断を追加し回避
		//   → BehaviorCollection::_methods に登録される順番が問題になる(先優先)
		//      → getMapメソッドがNc2ToNc3DividedBaseLanguageBehaviorで登録されるので、Magic methos(__call)で対応を試みた。
		//         → publicメソッドだったgetMapを削除したため、そもそも、BehaviorCollection::dispatchMethodから呼ばれなくなるのでダメ。
		//   → 継承先のBehaviorでgetMapを定義するしかさなそう。
		//   → もしくは、Nc2ToNc3AppModelで、分割したBehaviorをロードする処理を入れるかになる
		//       分割による影響の処理をNc2ToNc3AppModelで吸収するのは、処理が分散して分かりづらいのでやりたくない。
		//   → BaseBehaviorの分割は、呼び出し側の修正も考慮してやった方が良い。 ということで、一旦commit →すぐ戻す
		$actsAs = [
			'Nc2ToNc3.Nc2ToNc3DividedBaseLanguage'
		];
		if ($model->Behaviors->loaded($actsAs[0])) {
			return;
		}
		$model->Behaviors->init($model->alias, $actsAs);
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
 * Convert nc2 display_days.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted Nc3 value.
 */
	public function convertChoiceValue(Model $model, $nc2Value, $nc3Choices) {
		return $this->_convertDisplayDays($nc2Value, $nc3Choices);
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
 * Convert nc3 date.
 *
 * @param string $date Nc2 date.
 * @return Model converted date.
 */
	protected function _convertDate($date) {
		if (strlen($date) != 14) {
			return null;
		}

		// YmdHis → Y-m-d H:i:s　
		$date = substr($date, 0, 4) . '-' .
				substr($date, 4, 2) . '-' .
				substr($date, 6, 2) . ' ' .
				substr($date, 8, 2) . ':' .
				substr($date, 10, 2) . ':' .
				substr($date, 12, 2);

		return $date;
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
 * Convert nc2 display_days.
 *
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	protected function _convertDisplayDays($displayDays) {
		if (!$displayDays) {
			return null;
		}
		$arr = [30, 14, 7, 3, 1];
		foreach ($arr as $num) {
			if ($displayDays >= $num) {
				$displayDays = $num;
				break;
			}
		}
		return $displayDays;
	}

/**
 * Convert nc2 choice value.
 *
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted Nc3 value.
 */
	protected function _convertChoiceValue($nc2Value, $nc3Choices) {
		if (!$nc2Value) {
			return null;
		}

		$nc3Choices = rsort($nc3Choices, SORT_NUMERIC);
		foreach ($nc3Choices as $nc3Choice) {
			if ($nc2Value >= $nc3Choice) {
				$nc2Value = $nc3Choice;
				break;
			}
		}

		return $nc2Value;
	}

}