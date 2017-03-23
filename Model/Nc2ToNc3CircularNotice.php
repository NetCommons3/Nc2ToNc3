<?php
/**
 * Nc2ToNc3CircularNotice
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3CircularNotice
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
class Nc2ToNc3CircularNotice extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3CircularNotice'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'CircularNotice Migration start.'));

		/* @var $Nc2CircularBlock AppModel */
		$Nc2CircularBlock = $this->getNc2Model('circular_block');
		$nc2CircularBlocks = $Nc2CircularBlock->find('all');

		if (!$this->__saveNc3CircularNoticeFrameSettingFromNc2($nc2CircularBlocks)) {
			return false;
		}

		$Nc2Circular = $this->getNc2Model('circular');
		$nc2Circulars = $Nc2Circular->find('all');

		if (!$this->__saveNc3CircularNoticeContentFromNc2($nc2Circulars)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'CircularNotice Migration end.'));
		return true;
	}

/**
 * Save CircularNoticeFrameSetting from Nc2.
 *
 * @param array $nc2CircularBlocks Nc2CircularBlock data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3CircularNoticeFrameSettingFromNc2($nc2CircularBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNoticeFrameSetting data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$CircularNoticeFrame = ClassRegistry::init('CircularNotices.CircularNoticeFrameSetting');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$CircularNoticeFrame->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$CircularNoticeFrame->Behaviors->Block->settings = $CircularNoticeFrame->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		$CircularNoticeSet = ClassRegistry::init('CircularNotices.CircularNoticeSetting');

		foreach ($nc2CircularBlocks as $nc2CircularBlock) {
			/** @var array $nc2CircularBlock */
			if (!$nc2CircularBlock) {
				continue;
			}

			//saveCircularNoticeFrameSettingではblock_idが追加されないため、setCircularNoticeSettingを実行、
			$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
			$nc2BlockId = $nc2CircularBlock['Nc2CircularBlock']['block_id'];
			$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);

			if (!$CircularNoticeSet->setCircularNoticeSetting($frameMap['Frame']['id'])) {
				return false;
			}

			$CircularNoticeFrame->begin();
			try {
				$data = $this->generateNc3CircularNoticeFrameSettingData($nc2CircularBlock);
				if (!$data) {
					$CircularNoticeFrame->rollback();
					continue;
				}

				//$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				$nc3RoomId = $data['Block']['room_id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				Current::write('Plugin.key', 'circular_notices');

				$BlocksLanguage->create();
				$CircularNoticeFrame->create();
				$Block->create();
				$Topic->create();

				if (!$CircularNoticeFrame->saveCircularNoticeFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2CircularBlock) . "\n" .
						var_export($CircularNoticeFrame->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2CircularBlockId = $nc2CircularBlock['Nc2CircularBlock']['block_id'];
				$idMap = [];
				$idMap = [
					$nc2CircularBlockId => $CircularNoticeFrame->id
				];
				$this->saveMap('CircularNoticeFrameSetting', $idMap);

				$nc2CircularRoomId = $nc2CircularBlock['Nc2CircularBlock']['room_id'];
				$idMap = [];
				$idMap = [
					$nc2CircularRoomId => $data['Block']['room_id']
				];
				$this->saveMap('Room', $idMap);

				$CircularNoticeFrame->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CircularNoticeFrame::savePage()でthrowされるとこの処理に入ってこない
				$CircularNoticeFrame->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNoticeFrameSetting data Migration end.'));
		return true;
	}

/**
 * Save Circular from Nc2.
 *
 * @param array $nc2Circulars Nc2Circular data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3CircularNoticeContentFromNc2($nc2Circulars) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNoticeContent data Migration start.'));

		/* @var $CircularNoticeCont CircularNoticeContent */
		$CircularNoticeCont = ClassRegistry::init('CircularNotices.CircularNoticeContent');

		//Announcement モデルで	BlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$CircularNoticeEntry->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$CircularNoticeEntry->Behaviors->Block->settings = $CircularNoticeEntry->actsAs['Blocks.Block'];

		//$Nc2CircularChoice = $this->getNc2Model('circular_choice');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Circulars as $nc2Circular) {
			//$nc2CircularChoice = $Nc2CircularChoice->findByCircularId($nc2Circular['Nc2Circular']['circular_id'], null, null, -1);

			$CircularNoticeCont->begin();
			try {
				$data = $this->generateNc3CircularNoticeContentData($nc2Circular);
				if (!$data) {
					$CircularNoticeCont->rollback();
					continue;
				}

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				Current::write('Room.id', $nc3RoomId);
				Current::write('Plugin.key', 'circular_notices');

				$BlocksLanguage->create();
				$CircularNoticeCont->create();
				$Block->create();
				$Topic->create();

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				if (!$CircularNoticeCont->saveCircularNoticeContent($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2Circular) . "\n" .
						var_export($CircularNoticeCont->validationErrors, true);
					$this->writeMigrationLog($message);
					continue;
				}

				// Hash::merge で BlogEntry::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Blogs/blob/3.1.0/Model/BlogEntry.php#L138-L141
				$CircularNoticeCont->validate = [];

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2CircularId = $nc2Circular['Nc2Circular']['circular_id'];
				$idMap = [
					$nc2CircularId => $CircularNoticeCont->id
				];
				$this->saveMap('CircularNoticeContent', $idMap);
				$CircularNoticeCont->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CircularNoticeFrame::savePage()でthrowされるとこの処理に入ってこない
				$CircularNoticeCont->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNoticeContent data Migration end.'));
		return true;
	}

}