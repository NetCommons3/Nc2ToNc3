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
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)]
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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Blog',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Blog Migration start.'));

		/* @var $Nc2Blog AppModel */
		$Nc2Journal = $this->getNc2Model('journal');
		$nc2Journals = $Nc2Journal->find('all');
		if (!$this->__saveNc3BlogFromNc2($nc2Journals)) {
			return false;
		}

		/* @var $Nc2JournalBlock AppModel */
		$Nc2JournalBlock = $this->getNc2Model('journal_block');
		$nc2JournalBlocks = $Nc2JournalBlock->find('all');
		if (!$this->__saveNc3BlogFrameSettingFromNc2($nc2JournalBlocks)) {
			return false;
		}

		$Nc2JournalPost = $this->getNc2Model('journal_post');
		$nc2JournalPosts = $Nc2JournalPost->findAllByParentId('0', null, null, null, null, -1);

		if (!$this->__saveNc3BlogEntryFromNc2($nc2JournalPosts)) {
			return false;
		}

		$query = [
			'conditions' => [
				'NOT' => [
					'parent_id' => '0'
				]
			],
			'recursive' => -1
		];
		$nc2JournalPosts = $Nc2JournalPost->find('all', $query);
		if (!$this->__saveNc3ContentCommentFromNc2($nc2JournalPosts)) {
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

		/* @var $Blog Blog */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Blog = ClassRegistry::init('Blogs.Blog');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

		Current::write('Plugin.key', 'blogs');
		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Blog->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Blog->Behaviors->Block->settings = $Blog->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Journals as $nc2Journal) {
			$Blog->begin();
			try {
				$data = $this->generateNc3BlogData($nc2Journal);
				if (!$data) {
					$Blog->rollback();
					continue;
				}
				$query['conditions'] = [
					'journal_id' => $nc2Journal['Nc2Journal']['journal_id']
				];
				$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('journal_category', $query);
				$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);

				// いる？
				$nc3RoomId = $data['Block']['room_id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$Blog->create();
				$Block->create();
				$Topic->create();

				if (!$Blog->saveBlog($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Blog->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Journal) . "\n" .
						var_export($Blog->validationErrors, true);
					$this->writeMigrationLog($message);
					$Blog->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2JournalId = $nc2Journal['Nc2Journal']['journal_id'];
				$idMap = [
					$nc2JournalId => $Blog->id
				];
				$this->saveMap('Blog', $idMap);

				$nc3Blog = $Blog->findById($Blog->id, 'block_id', null, -1);
				if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $nc3Blog['Blog']['block_id'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Journal);
					$this->writeMigrationLog($message);
					$Blog->rollback();
					continue;
				}

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
 * Save BlogFrameSetting from Nc2.
 *
 * @param array $nc2JournalBlocks Nc2ournalBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3BlogFrameSettingFromNc2($nc2JournalBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  BlogFrameSetting data Migration start.'));

		/* @var $BlogFrameSetting BlogFrameSetting */
		/* @var $Frame Frame */
		$BlogFrameSetting = ClassRegistry::init('Blogs.BlogFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2JournalBlocks as $nc2JournalBlock) {
			$BlogFrameSetting->begin();
			try {
				$data = $this->generateNc3BlogFrameSettingData($nc2JournalBlock);
				if (!$data) {
					$BlogFrameSetting->rollback();
					continue;
				}

				$BlogFrameSetting->create();
				if (!$BlogFrameSetting->saveBlogFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalBlock) . "\n" .
						var_export($BlogFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$BlogFrameSetting->rollback();
					continue;
				}

				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalBlock) . "\n" .
						var_export($BlogFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$BlogFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $BlogFrameSetting->id
				];
				$this->saveMap('BlogFrameSetting', $idMap);

				$BlogFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::saveBlogFrameSetting()でthrowされるとこの処理に入ってこない
				$BlogFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		/*
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');
		*/

		$this->writeMigrationLog(__d('nc2_to_nc3', '  BlogFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save BlogEntry from Nc2.
 *
 * @param array $nc2JournalPosts Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3BlogEntryFromNc2($nc2JournalPosts) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Entry data Migration start.'));

		/* @var $BlogEntry BlogEntry */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$BlogEntry = ClassRegistry::init('Blogs.BlogEntry');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

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
			$BlogEntry->begin();
			try {
				$data = $this->generateNc3BlogEntryData($nc2JournalPost);
				if (!$data) {
					$BlogEntry->rollback();
					continue;
				}
				$nc3BlockId = $data['Block']['id'];
				$nc2CategoryId = $nc2JournalPost['Nc2JournalPost']['category_id'];
				$data['BlogEntry']['category_id'] = $Nc2ToNc3Category->getNc3CategoryId($nc3BlockId, $nc2CategoryId);

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($nc3BlockId, null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block.id', $nc3BlockId);
				Current::write('Room.id', $nc3RoomId);

				$BlocksLanguage->create();
				$BlogEntry->create();
				$Block->create();
				$Topic->create();

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				$nc3Status = $data['BlogEntry']['status'];
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = ($nc3Status != 2);

				// Hash::merge で BlogEntry::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Blogs/blob/3.1.0/Model/BlogEntry.php#L138-L141
				$BlogEntry->validate = [];

				if (!$BlogEntry->saveEntry($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$BlogEntry->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2JournalPost) . "\n" .
						var_export($BlogEntry->validationErrors, true);
					$this->writeMigrationLog($message);
					$BlogEntry->rollback();
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

		Current::remove('Block.id');
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Entry data Migration end.'));
		return true;
	}

/**
 * Save ContentComment from Nc2.
 *
 * @param array $nc2JournalPosts Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3ContentCommentFromNc2($nc2JournalPosts) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Content Comment data Migration start.'));

		/* @var $ContentComment ContentComment */
		/* @var $Nc2ToNc3Comment Nc2ToNc3ContentComment */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$ContentComment = ClassRegistry::init('ContentComments.ContentComment');
		$Nc2ToNc3Comment = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3ContentComment');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		foreach ($nc2JournalPosts as $nc2JournalPost) {
			$ContentComment->begin();
			try {
				$data = $this->generateNc3ContentCommentData($nc2JournalPost);
				if (!$data) {
					$ContentComment->rollback();
					continue;
				}

				$nc2RoomId = $nc2JournalPost['Nc2JournalPost']['room_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomId);
				$nc3RoomId = $mapIdList[$nc2RoomId];
				$nc3Status = $data['ContentComment']['status'];

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = ($nc3Status != 2);

				$ContentComment->create();
				// 一応Model::validatの初期化
				$ContentComment->validate = [];

				if (!$ContentComment->saveContentComment($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalPost) . "\n" .
						var_export($ContentComment->validationErrors, true);
					$this->writeMigrationLog($message);

					$ContentComment->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
				$idMap = [
					$nc2PostId => $ContentComment->id
				];
				if (!$Nc2ToNc3Comment->saveContentCommentMap($idMap, $data['ContentComment']['block_key'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalPost);
					$this->writeMigrationLog($message);

					$ContentComment->rollback();
					continue;
				}

				$ContentComment->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				$ContentComment->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Content Comment Migration end.'));

		return true;
	}

}
