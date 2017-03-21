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
class Nc2ToNc3CircularNotice extends Nc2ToNc3AppModel
{

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
	public function migrate()
	{
		$this->writeMigrationLog(__d('nc2_to_nc3', 'CircularNotice Migration start.'));

		/* @var $Nc2CircularNotice AppModel */

		/* @var $Nc2JournalBlock AppModel */
		//var_dump($this->settings);exit;
		$Nc2CircularBlock = $this->getNc2Model('circular_block');
		$nc2CircularBlocks = $Nc2CircularBlock->find('all');

		if (!$this->__saveNc3CircularNoticeFrameSettingFromNc2($nc2CircularBlocks)) {
			return false;
		}
/*
		$Nc2JournalPost = $this->getNc2Model('journal_post');
		$nc2JournalPosts = $Nc2JournalPost->find('all');

        if (!$this->__saveNc3CircularNoticeEntryFromNc2($nc2JournalPosts)) {
            return false;
		}
*/
		$this->writeMigrationLog(__d('nc2_to_nc3', 'CircularNotice Migration end.'));
		return true;
	}

	/**
	 * Save JournalFrameSetting from Nc2.
	 *
	 * @param array $nc2Journals Nc2Journal data.
	 * @return bool True on success
	 * @throws Exception
	 */

	private function __saveNc3CircularNoticeFrameSettingFromNc2($nc2CircularBlocks)
	{
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNoticeFrameSetting data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$CircularNoticeFrameSetting = ClassRegistry::init('CircularNotices.CircularNoticeFrameSetting');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$CircularNoticeFrameSetting->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$CircularNoticeFrameSetting->Behaviors->Block->settings = $CircularNoticeFrameSetting->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2CircularBlocks as $nc2CircularBlock) {
			/** @var array $nc2CircularBlock */
			//var_dump($Nc2JournalBlock);exit;
			//var_dump($nc2Journal['Nc2Journal']['journal_id']);exit;
			if (!$nc2CircularBlock){
				continue;
			}
			$CircularNoticeFrameSetting->begin();
			try {
				$data = $this->generateNc3CircularNoticeFrameSettingData($nc2CircularBlock);
				if (!$data) {
					$CircularNoticeFrameSetting->rollback();
					continue;
				}

				$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				$nc3RoomId = $data['Block']['room_id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				Current::write('Plugin.key', 'circular_notices');

				$BlocksLanguage->create();
				$CircularNoticeFrameSetting->create();
				$Block->create();
				$Topic->create();

				if (!$CircularNoticeFrameSetting->saveCircularNoticeFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2CircularBlock) . "\n" .
						var_export($CircularNoticeFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
				Current::remove('Room.id', $nc3RoomId);
				Current::remove('Plugin.key', 'circular_notices');

				$nc2CircularBlockId = $nc2CircularBlock['Nc2CircularBlock']['block_id'];
				$idMap = [
					$nc2CircularBlockId => $CircularNoticeFrameSetting->id
				];
				$this->saveMap('CircularNoticeFrameSetting', $idMap);
				$CircularNoticeFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CircularNoticeFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$CircularNoticeFrameSetting->rollback($ex);
				throw $ex;
			}
		}
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNoticeFrameSetting data Migration end.'));
		return true;
	}

	/**
	 * Save JournalPost from Nc2.
	 *
	 * @param array $nc2JournalPosts Nc2JournalPost data.
	 * @return bool True on success
	 * @throws Exception
	 */

	private function __saveNc3CircularNoticeEntryFromNc2($nc2JournalPosts)
	{
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNotice Entry data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$CircularNoticeEntry = ClassRegistry::init('CircularNotices.CircularNoticeEntry');

		//Announcement モデルで	BlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$CircularNoticeEntry->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$CircularNoticeEntry->Behaviors->Block->settings = $CircularNoticeEntry->actsAs['Blocks.Block'];

		//$Nc2Journal = $this->getNc2Model('journal');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2JournalPosts as $nc2JournalPost) {
			//$nc2Journal = $Nc2Journal->findByJournalId($nc2JournalBlock['Nc2JournalBlock']['journal_id'], null, null, -1);

			$CircularNoticeEntry->begin();
			try {
				$data = $this->generateNc3CircularNoticeEntryData($nc2JournalPost);
				if (!$data) {
					$CircularNoticeEntry->rollback();
					continue;
				}

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];
//				var_dump($nc3RoomId );exit;

				Current::write('Room.id', $nc3RoomId);
				Current::write('Plugin.key', 'CircularNotices');

				$BlocksLanguage->create();
				$CircularNoticeEntry->create();
				$Block->create();
				$Topic->create();

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				if (!$CircularNoticeEntry->saveEntry($data)) {
					var_dump('SHIPPAI');exit;
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2JournalPost) . "\n" .
						var_export($CircularNoticeEntry->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
				Current::remove('Room.id', $nc3RoomId);
				Current::remove('Plugin.key', 'CircularNotices');

				$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
				$idMap = [
					$nc2PostId => $CircularNoticeEntry->id
				];
				$this->saveMap('CircularNoticeEntry', $idMap);
				$CircularNoticeEntry->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CircularNoticeFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$CircularNoticeEntry->rollback($ex);
				throw $ex;
			}
		}
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CircularNotice Entry data Migration end.'));
		return true;
	}
/**
 * Get Log argument.
 *
 * @param array $nc2JournalBlock Nc2JournalBlock data
 * @return string Log argument
 */
	public function getLogArgument($nc2JournalBlock) {
		return 'Nc2JournalBlock ' .
			'block_id:' . $nc2JournalBlock['Nc2JournalBlock']['block_id'];
	}

}