<?php
/**
 * Nc2ToNc3Video
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Video
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
class Nc2ToNc3Video extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Video'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Video Migration start.'));

		/* @var $Nc2Video AppModel */

		/* @var $Nc2MultimediaBlock AppModel */
		$Nc2Multimedia = $this->getNc2Model('multimedia');
		$nc2Multimedias = $Nc2Multimedia->find('all');

		if (!$this->__saveNc3VideoSettingFromNc2($nc2Multimedias)) {
			return false;
		}
		$Nc2MultimediaItem = $this->getNc2Model('multimedia_item');
		$nc2MultimediaItems = $Nc2MultimediaItem->find('all');

		if (!$this->__saveNc3VideoDataFromNc2($nc2MultimediaItems)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Video Migration end.'));
		return true;
	}

/**
 * Save Multimedia from Nc2.
 *
 * @param array $nc2Multimedias Nc2Multimedia data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3VideoSettingFromNc2($nc2Multimedias) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Video Setting data Migration start.'));

		/* @var $MultimediaFrameSetting MultimediaFrameSetting */
		//$VideoFrameSetting = ClassRegistry::init ('Videos.VideoFrameSetting');
		$VideoSetting = ClassRegistry::init('Videos.VideoSetting');

		Current::write('Plugin.key', 'videos');
		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$VideoSetting->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$VideoSetting->Behaviors->Block->settings = $VideoSetting->actsAs['Blocks.Block'];

		$Nc2MultimediaBlock = $this->getNc2Model('multimedia_block');

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		$Frame = ClassRegistry::init('Frames.Frame');

		foreach ($nc2Multimedias as $nc2Multimedia) {
			/** @var array $nc2MultimediaBlock */
			$nc2MultimediaBlock = $Nc2MultimediaBlock->findByMultimediaId($nc2Multimedia['Nc2Multimedia']['multimedia_id'], null, null, -1);
			if (!$nc2Multimedia) {
				continue;
			}
			try {
				$data = $this->generateNc3VideoSettingData($nc2Multimedia, $nc2MultimediaBlock);
				if (!$data) {
					$VideoSetting->rollback();
					continue;
				}

				//$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				//$nc3Room = $Nc2ToNc3Room->getMap($nc2Multimedia['Nc2Multimedia']['room_id']);

				$nc3RoomId = $data['Block']['room_id'];
				//$nc3RoomId = $nc3Room['Room']['id'];
				Current::write('Room.id', $nc3RoomId);

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				//$VideoFrameSetting->create();
				$VideoSetting->create();
				$Block->create();
				$Topic->create();
				$Frame->create();

				if (!$VideoSetting->saveVideoSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$VideoSetting->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2Multimedia) . "\n" .
						var_export($VideoSetting->validationErrors, true);
					$this->writeMigrationLog($message);
					$VideoSetting->rollback();
					continue;
				}
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2MultimediaId = $nc2Multimedia['Nc2Multimedia']['multimedia_id'];
				$idMap = [
					$nc2MultimediaId => $VideoSetting->id
				];
				$this->saveMap('VideoSetting', $idMap);

				$VideoSetting->commit();

			} catch(Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $VideoFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$VideoSetting->rollback($ex);
				throw $ex;
			}

			//saveFrameSettingするため$dataを引き渡す。同じ$dataに対してsaveVideoSettingとsaveVideoSettingと
			//saveVideoFrameSettingの両方を実行するため
			//@see https://github.com/NetCommons3/Videos/blob/master/Controller/VideoBlocksController.php#L118-L119
			if (!$this->__saveNc3VideoFrameSettingFromNc2($data, $nc2Multimedia)) {
				return false;
			}
			Current::remove('Room.id');
			Current::remove('Plugin.key');

			$this->writeMigrationLog(__d('nc2_to_nc3', '  Video Setting data Migration end.'));
			return true;
		}
	}

/**
 * Save Multimedia from Nc2.
 *
 * @param array $data Nc3 VideoSetting data.
 * @param array $nc2Multimedia Nc2Multimedia data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3VideoFrameSettingFromNc2($data, $nc2Multimedia) {
		$VideoFrameSetting = ClassRegistry::init('Videos.VideoFrameSetting');
		//$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		//$Block = ClassRegistry::init('Blocks.Block');

		$VideoFrameSetting->begin();
		try {

			//$BlocksLanguage->create();
			//$Block->create();
			$VideoFrameSetting->create();
			if (!$VideoFrameSetting->saveVideoFrameSetting($data)) {
				// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
				// var_exportは大丈夫らしい。。。
				// @see https://phpmd.org/rules/design.html

				$message = $this->getLogArgument($nc2Multimedia) . "\n" .
					var_export($VideoFrameSetting->validationErrors, true);
				$this->writeMigrationLog($message);
				$VideoFrameSetting->rollback();
				return false;
			}

			$nc2MultimediaId = $nc2Multimedia['Nc2Multimedia']['multimedia_id'];
			$idMap = [
				$nc2MultimediaId => $VideoFrameSetting->id
			];
			$this->saveMap('VideoFrameSetting', $idMap);
			$VideoFrameSetting->commit();
		} catch(Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $VideoFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$VideoFrameSetting->rollback($ex);
				throw $ex;
		}
		return true;
	}

/**
 * Save MultimediaItem from Nc2.
 *
 * @param array $nc2MultimediaItems Nc2MultimediaItem data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3VideoDataFromNc2($nc2MultimediaItems) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Video data Migration start.'));

		/* @var $MultimediaFrameSetting MultimediaFrameSetting */
		$Video = ClassRegistry::init('Videos.Video');

		Current::write('Plugin.key', 'Videos');
		//Announcement モデルで	BlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$VideoEntry->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$VideoEntry->Behaviors->Block->settings = $VideoEntry->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		$VideoSetting = ClassRegistry::init('Videos.VideoSetting');

		foreach ($nc2MultimediaItems as $nc2MultimediaItem) {

			$Video->begin();
			try {
				$data = $this->generateNc3VideoData($nc2MultimediaItem);
				if (!$data) {
					$Video->rollback();
					continue;
				}

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				Current::write('Room.id', $nc3RoomId);
				Current::write('Block.key', $data['Block']['key']);

				$BlocksLanguage->create();
				$Video->create();
				$VideoSetting->create();
				$Block->create();
				$Topic->create();

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// Hash::merge で VideoEntry::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Videos/blob/3.1.0/Model/VideoEntry.php#L138-L141
				$Video->validate = [];

				if (!$Video->saveVideo($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Video->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2MultimediaItem) . "\n" .
						var_export($Video->validationErrors, true);
					$this->writeMigrationLog($message);
					$Video->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2ItemId = $nc2MultimediaItem['Nc2MultimediaItem']['item_id'];
				$idMap = [
					$nc2ItemId => $Video->id
				];
				$this->saveMap('Video', $idMap);
				$Video->commit();

			} catch(Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $VideoFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Video->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');
		Current::remove('Plugin.key');
		Current::remove('Block.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Video data Migration end.'));
		return true;
	}

}