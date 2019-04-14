<?php
/**
 * Nc2ToNc3Frame
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Frame
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
class Nc2ToNc3Frame extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Frame'];

/**
 * Number of validation error
 *
 * @var int
 */
	private $__numberOfValidationError = 0;

/**
 * Migration method.
 *
 * @return bool True on success.
 * @throws Exception
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Frame Migration start.'));

		if (!$this->validates()) {
			return false;
		}

		if (!$this->__saveFrameFromNc2WhileDividing()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Frame Migration end.'));
		return true;
	}

/**
 * Save Frame from Nc2 while dividing.
 *
 * @return bool True on success.
 * @throws Exception
 */
	private function __saveFrameFromNc2WhileDividing() {
		$limit = 1000;

		/* @var $Nc2Block AppModel */
		$Nc2Block = $this->getNc2Model('blocks');
		$query = [
			'conditions' => [
				'Nc2Block.module_id !=' => '0',
				'or' => [
					// デフォルトのメニュー、imagineは移行しない方が良いと思う。
					'Nc2Block.block_id >' => '5',
					// Nc2Blockデータ（お知らせ３つ = block_id:1 ヘッダーのNCロゴ、block_id:2 検索リンク、block_id:3 welcometo）は書き換えて再利用しているパターンがあるため移行する
					'Nc2Block.block_id' => ['1', '2', '3']
				]
			],
			// Nc2Block.parent_idから再帰処理するのと同じ結果になると思う。
			// Frame::saveFrame で　追加時は一番上に配置する処理があるため、どうするのが良いのか？
			// @see https://github.com/NetCommons3/Frames/blob/3.1.0/Model/Frame.php#L230-L232
			// とりあえず、降順で処理しとく
			'order' => [
				'Nc2Block.parent_id DESC',
				'Nc2Block.row_num DESC',
				'Nc2Block.col_num DESC',
			],
			'limit' => $limit,
			'offset' => 0,
		];

		$numberOfBlocks = 0;
		//$numberOfUsers = $Nc2Block->find('count', $query);
		//$query['limit'] = $limit;
		while ($nc2Blocks = $Nc2Block->find('all', $query)) {
			if (!$this->__saveFrameFromNc2($nc2Blocks)) {
				return false;
			}

			$numberOfBlocks += count($nc2Blocks);
			$errorRate = round($this->__numberOfValidationError / $numberOfBlocks);
			// 5割エラー発生で止める
			if ($errorRate >= 0.5) {
				$this->validationErrors = [
					'database' => [
						__d('nc2_to_nc3',
							'Many error data. Please check the log. %s',
							['ValidationErrorCount: ' . $this->__numberOfValidationError . ' Nc2BlockCount: ' . $numberOfBlocks])
					]
				];
				return false;
			}

			$query['offset'] += $limit;
		}

		return true;
	}

/**
 * Save Frame from Nc2.
 *
 * @param array $nc2Blocks Nc2Block data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveFrameFromNc2($nc2Blocks) {
		/* @var $Frame Frame */
		/* @var $Room Room */
		/* @var $Block Block */
		$Frame = ClassRegistry::init('Frames.Frame');
		$Room = ClassRegistry::init('Rooms.Room');
		$Block = ClassRegistry::init('Blocks.Block');

		//$this->saveExistingMap($nc2Blocks);
		$nc3CurrentRoom = null;
		foreach ($nc2Blocks as $nc2Block) {
			$Frame->begin();
			try {
				/*
				if (!$this->isMigrationRow($nc2Block)) {
					$Frame->rollback();
					continue;
				}*/

				$data = $this->__generateNc3Data($nc2Block);
				if (!$data) {
					$Frame->rollback();
					continue;
				}

				// Frame::saveFrameから、各プラグインのafterFrameSaveが呼び出され、その中で参照している値を一時書き換え
				// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/Calendar.php#L333
				if (!$nc3CurrentRoom) {
					$nc3CurrentRoom = Current::read('Room');
				}
				$nc3Room = $Room->findById($data['Frame']['room_id'], null, null, -1);
				Current::write('Room', $nc3Room['Room']);
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.3/Model/Questionnaire.php#L442
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.3/Model/QuestionnaireSetting.php#L138-L141
				$Block->create(false);

				$Frame->create(false);
				if (!($data = $Frame->saveFrame($data))) {
					// Frame::saveFrameではreturn falseなし。Frame->saveの戻り値が空の場合はありえる。

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Block) . "\n" .
						var_export($Frame->validationErrors, true);
					$this->writeMigrationLog($message);

					$this->__numberOfValidationError++;

					$Frame->rollback();
					continue;
				}

				$nc2BlockId = $nc2Block['Nc2Block']['block_id'];
				if ($this->getMap($nc2BlockId)) {
					$Frame->commit();
					continue;
				}

				$idMap = [
					$nc2BlockId => $Frame->id
				];
				$this->saveMap('Frame', $idMap);

				$Frame->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $Frame::saveUser()でthrowされるとこの処理に入ってこない
				$Frame->rollback($ex);

				// 一時書き換えた値を戻す。
				if (!$nc3CurrentRoom) {
					Current::write('Room', $nc3CurrentRoom);
				}

				throw $ex;
			}
		}

		// 一時書き換えた値を戻す。
		if (!$nc3CurrentRoom) {
			Current::write('Room', $nc3CurrentRoom);
		}

		return true;
	}

/**
 * Generate Frame data for Nc3 Plugin data .
 *
 * @param array $nc2Block Nc2Block data.
 * @return array $nc3Frame data
 */
	private function __generateNc3Data($nc2Block) {
		if ($this->getMap($nc2Block['Nc2Block']['block_id'])) {
			// Log出力すると大量
			//$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Block));
			//$this->writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Page Nc2ToNc3Page */
		$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');
		$nc2PageId = $nc2Block['Nc2Block']['page_id'];
		$pageMap = $Nc2ToNc3Page->getMap($nc2PageId);
		if (!$pageMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Block));
			$this->writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Plugin Nc2ToNc3Plugin */
		$Nc2ToNc3Plugin = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Plugin');
		$pluginMap = $Nc2ToNc3Plugin->getMap($nc2Block['Nc2Block']['module_id']);
		if (!$pluginMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Block));
			$this->writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$nc2Page = $Nc2Page->findByPageId($nc2PageId, 'lang_dirname', null, -1);
		$nc3LanguageId = $this->convertLanguage($nc2Page['Nc2Page']['lang_dirname']);
		if (!$nc3LanguageId) {
			$nc3LanguageId = $this->getLanguageIdFromNc2();
		}

		$data = [
			'Frame' => [
				'room_id' => $pageMap['Box']['room_id'],
				'box_id' => $pageMap['Box']['id'],
				'plugin_key' => $pluginMap['Plugin']['key'],
				'is_deleted' => '0',
				'language_id' => $nc3LanguageId
			],
			'FramesLanguage' => [
				'language_id' => $nc3LanguageId,
				'name' => $nc2Block['Nc2Block']['block_name']
			],
			// @see https://github.com/NetCommons3/Frames/blob/3.1.0/Controller/FramesController.php#L67
			'FramePublicLanguage' => [
				'language_id' => ['0']
			]
		];

		return $data;
	}
}
