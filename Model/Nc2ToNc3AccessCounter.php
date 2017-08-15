<?php
/**
 * Nc2ToNc3AccessCounter
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3AccessCounter
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
 * @method void changeNc3CurrentLanguage($langDirName = null)
 * @method void restoreNc3CurrentLanguage()
 *
 */
class Nc2ToNc3AccessCounter extends Nc2ToNc3AppModel {

/**
 * Custom database table name, or null/false if no table association is desired.
 *
 * @var string
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#usetable
 */
	public $useTable = false;

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Base'
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'AccessCounter Migration start.'));

		/* @var $Nc2Counter AppModel */
		$Nc2Counter = $this->getNc2Model('counter');
		$nc2Counters = $Nc2Counter->find('all');
		if (!$this->__saveAccessCounterFromNc2($nc2Counters)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'AccessCounter Migration end.'));
		return true;
	}

/**
 * Save AccessCounter from Nc2.
 *
 * @param array $nc2Counters Nc2Counter data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveAccessCounterFromNc2($nc2Counters) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  AccessCounter data Migration start.'));

		/* @var $AccessCounter AccessCounter */
		/* @var $CounterFrameSetting AccessCounterFrameSetting */
		$AccessCounter = ClassRegistry::init('AccessCounters.AccessCounter');
		$CounterFrameSetting = ClassRegistry::init('AccessCounters.AccessCounterFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//@see https://github.com/NetCommons3/AccessCounters/blob/3.1.3/Model/AccessCounter.php#L35
		$AccessCounter->Behaviors->Block->settings = [];

		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		foreach ($nc2Counters as $nc2Counter) {
			$AccessCounter->begin();

			$nc2Blockld = $nc2Counter['Nc2Counter']['block_id'];
			$nc3Frame = $Nc2ToNc3Frame->getMap($nc2Blockld);
			if (!$nc3Frame) {
				$AccessCounter->rollback();
				continue;
			}

			$data = $this->__generateNc3AccessCounterData($nc2Counter, $nc3Frame);
			if (!$data) {
				$AccessCounter->rollback();
				continue;
			}

			//AccessCounter テーブルの移行を実施。SAVE前にCurrentのデータを書き換えが必要なため
			$nc3RoomId = $nc3Frame['Frame']['room_id'];
			Current::write('Plugin.key', 'access_counters');
			Current::write('Room.id', $nc3RoomId);
			CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

			// Model::idを初期化しないとUpdateになってしまう。
			$AccessCounter->create();
			$Block->create();
			$BlocksLanguage->create();

			if (!$AccessCounter->saveAccessCounter($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、 ここでrollback
				$AccessCounter->rollback();

				$message = $this->getLogArgument($nc2Counter) . "\n" .
					var_export($AccessCounter->validationErrors, true);
				$this->writeMigrationLog($message);

				$AccessCounter->rollback();
				continue;
			}

			unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

			$data = $this->__generateNc3AccessCounterFrameSettingData($nc2Counter, $nc3Frame, $AccessCounter->id);
			$CounterFrameSetting->create();
			if (!$CounterFrameSetting->saveAccessCounterFrameSetting($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
				$AccessCounter->rollback();

				$message = $this->getLogArgument($nc2Counter) . "\n" .
					var_export($CounterFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

				$AccessCounter->rollback();
				continue;
			}

			if (!$Frame->saveFrame($data)) {
				$message = $this->getLogArgument($nc2Counter) . "\n" .
				var_export($Frame->validationErrors, true);
				$this->writeMigrationLog($message);

				$AccessCounter->rollback();
				continue;
			}

			$idMap = [
				$nc2Blockld => $AccessCounter->id
			];
			$this->saveMap('AccessCounter', $idMap);

			$AccessCounter->commit();
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  AccessCounter data Migration end.'));
		return true;
	}

/**
 * Generate AccessCounter data.
 *
 * @param array $nc2Counter Nc2Counter data.
 * @param array $nc3Frame Nc3Frame data.
 * @return bool True on success
 */
	private function __generateNc3AccessCounterData($nc2Counter, $nc3Frame) {
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		// Nc2Counterのshow_char_before,show_char_after,commentの優先順位でBlocksLanguage.nameとする。
		// すべてなければCounter
		$nc3BlockName = $nc2Counter['Nc2Counter']['show_char_before'];
		if (!strlen($nc3BlockName)) {
			$nc3BlockName = $nc2Counter['Nc2Counter']['show_char_after'];
		}
		if (!strlen($nc3BlockName)) {
			$nc3BlockName = $nc2Counter['Nc2Counter']['comment'];
		}
		if (!strlen($nc3BlockName)) {
			$nc3BlockName = 'Counter';
		}

		$data = [
			'AccessCounter' => [
				'count' => $nc2Counter['Nc2Counter']['counter_num'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Counter['Nc2Counter']),
				'created' => $this->convertDate($nc2Counter['Nc2Counter']['insert_time']),
			],
			'Block' => [
				'room_id' => $nc3Frame['Frame']['room_id'],
				'plugin_key' => 'access_counters'
			],
			'BlocksLanguage' => [
				'name' => $nc3BlockName,
			],
			'Frame' => [
				'id' => $nc3Frame['Frame']['id']
			]
		];

		$data = $this->__mergeExistData($nc2Counter, $data);

		return $data;
	}

/**
 * Generate AccessCounterFrameSetting data.
 *
 * @param array $nc2Counter Nc2Counter data.
 * @param array $nc3Frame Nc3Frame data.
 * @param string $nc3AccessCounterId Nc3AccessCounter id.
 * @return bool True on success
 */
	private function __generateNc3AccessCounterFrameSettingData($nc2Counter, $nc3Frame, $nc3AccessCounterId) {
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		$data['AccessCounterFrameSetting'] = [
			'frame_key' => $nc3Frame['Frame']['key'],
			'display_type' => '1',	// 対応できなのでとりあえず1
			'display_digit' => $nc2Counter['Nc2Counter']['counter_digit'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Counter['Nc2Counter']),
			'created' => $this->convertDate($nc2Counter['Nc2Counter']['insert_time']),
		];

		/* @var $AccessCounter AccessCounter */
		$AccessCounter = ClassRegistry::init('AccessCounters.AccessCounter');
		$nc3AccessCounter = $AccessCounter->findById($nc3AccessCounterId, 'Block.id', null, 0);
		$data['Frame'] = [
			'id' => $nc3Frame['Frame']['id'],
			'plugin_key' => 'access_counters',
			'block_id' => Hash::get($nc3AccessCounter, ['Block', 'id']),
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Counter Nc2Counter data
 * @return string Log argument
 */
	public function getLogArgument($nc2Counter) {
		return 'Nc2Counter ' .
			'block_id:' . $nc2Counter['Nc2Counter']['block_id'];
	}

/**
 * Merge exist data
 *
 * @param array $nc2Counter Nc2Counter data.
 * @param array $nc3AccessCounter Nc3AccessCounter data.
 * @return array Merge exist data.
 */
	private function __mergeExistData($nc2Counter, $nc3AccessCounter) {
		$AccessCounterMap = $this->__getMap($nc2Counter['Nc2Counter']['block_id']);
		if ($AccessCounterMap) {
			// 移行済み
			return [];

			// Debug用
			/*
			//$nc3AccessCounter['AccessCounter']['id'] = $AccessCounterMap['AccessCounter']['id'];
			$nc3AccessCounter['AccessCounter']['block_key'] = $AccessCounterMap['AccessCounter']['block_key'];

			$nc3AccessCounter['Block']['id'] = $AccessCounterMap['AccessCounter']['block_id'];
			$nc3AccessCounter['Block']['key'] = $AccessCounterMap['AccessCounter']['block_key'];
			*/
		}

		return $nc3AccessCounter;
	}

/**
 * Get map
 *
 * @param array|string $nc2Blocklds Nc2Counter block_id.
 * @return array Map data with Nc2Counter block_id as key.
 */
	private function __getMap($nc2Blocklds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $AccessCounter AccessCounter */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$AccessCounter = ClassRegistry::init('AccessCounters.AccessCounter');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('AccessCounter', $nc2Blocklds);
		$query = [
			'fields' => [
				'AccessCounter.id',
				'AccessCounter.block_key',
			],
			'conditions' => [
				'AccessCounter.id' => $mapIdList
			],
			'recursive' => -1,
		];
		$nc3AccessCounters = $AccessCounter->find('all', $query);
		if (!$nc3AccessCounters) {
			return $nc3AccessCounters;
		}

		$map = [];
		foreach ($nc3AccessCounters as $nc3AccessCounter) {
			$nc2Id = array_search($nc3AccessCounter['AccessCounter']['id'], $mapIdList);
			$map[$nc2Id] = $nc3AccessCounter;
		}

		if (is_string($nc2Blocklds)) {
			$map = $map[$nc2Blocklds];
		}

		return $map;
	}

}

