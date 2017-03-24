<?php
/**
 * Nc2ToNc3Blog
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Blog
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
class Nc2ToNc3Blog extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Blog'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Blog Migration start.'));

		/* @var $Nc2Blog AppModel */

		/* @var $Nc2JournalBlock AppModel */
		$Nc2Journal = $this->getNc2Model('journal');
		$nc2Journals = $Nc2Journal->find('all');

		if (!$this->__saveNc3BlogFromNc2($nc2Journals)) {
			return false;
		}

		$Nc2JournalPost = $this->getNc2Model('journal_post');
		$nc2JournalPosts = $Nc2JournalPost->find('all');

		if (!$this->__saveNc3BlogEntryFromNc2($nc2JournalPosts)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Blog Migration end.'));
		return true;
	}

/**
 * Save JournalFrameSetting from Nc2.
 *
 * @param array $nc2Journals Nc2Journal data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3BlogFromNc2($nc2Journals) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$Blog = ClassRegistry::init('Blogs.Blog');

		Current::write('Plugin.key', 'blogs');
		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Blog->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Blog->Behaviors->Block->settings = $Blog->actsAs['Blocks.Block'];

		$Nc2JournalBlock = $this->getNc2Model('journal_block');

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Journals as $nc2Journal) {
			/** @var array $nc2JournalBlock */
			//var_dump($Nc2JournalBlock);exit;
			//var_dump($nc2Journal['Nc2Journal']['journal_id']);exit;
			$nc2JournalBlock = $Nc2JournalBlock->findByJournalId($nc2Journal['Nc2Journal']['journal_id'], null, null, -1);
			if (!$nc2JournalBlock) {
				continue;
			}
			$Blog->begin();
			try {
				$data = $this->generateNc3BlogData($nc2Journal, $nc2JournalBlock);
				if (!$data) {
					$Blog->rollback();
					continue;
				}

				$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				$nc3Room = $Nc2ToNc3Room->getMap($nc2Journal['Nc2Journal']['room_id']);
				$nc3RoomId = $nc3Room['Room']['id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$Blog->create();
				$Block->create();
				$Topic->create();

				if (!$Blog->saveBlog($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2Journal) . "\n" .
						var_export($Blog->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2JournalId = $nc2Journal['Nc2Journal']['journal_id'];
				$idMap = [
					$nc2JournalId => $Blog->id
				];
				$this->saveMap('Blog', $idMap);
				$Blog->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Blog->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog data Migration end.'));
		return true;
	}

/**
 * Save JournalPost from Nc2.
 *
 * @param array $nc2JournalPosts Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3BlogEntryFromNc2($nc2JournalPosts) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Entry data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$BlogEntry = ClassRegistry::init('Blogs.BlogEntry');

		Current::write('Plugin.key', 'blogs');
		//Announcement モデルで	BlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$BlogEntry->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$BlogEntry->Behaviors->Block->settings = $BlogEntry->actsAs['Blocks.Block'];

		//$Nc2Journal = $this->getNc2Model('journal');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2JournalPosts as $nc2JournalPost) {

			//root_idが0以外は、コメントデータにあたり、NC3-content_commentsへ移行する。
			//Nc2ToNc3Commentクラス追加までは、暫定対応として処理しないようにする
			if ($nc2JournalPost['Nc2JournalPost']['root_id']) {
				continue;
			}

			$BlogEntry->begin();
			try {
				$data = $this->generateNc3BlogEntryData($nc2JournalPost);
				if (!$data) {
					$BlogEntry->rollback();
					continue;
				}

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				Current::write('Room.id', $nc3RoomId);

				$BlocksLanguage->create();
				$BlogEntry->create();
				$Block->create();
				$Topic->create();

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// Hash::merge で BlogEntry::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Blogs/blob/3.1.0/Model/BlogEntry.php#L138-L141
				$BlogEntry->validate = [];

				if (!$BlogEntry->saveEntry($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2JournalPost) . "\n" .
						var_export($BlogEntry->validationErrors, true);
					$this->writeMigrationLog($message);
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
				$idMap = [
					$nc2PostId => $BlogEntry->id
				];
				$this->saveMap('BlogEntry', $idMap);
				$BlogEntry->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$BlogEntry->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Entry data Migration end.'));
		return true;
	}

}