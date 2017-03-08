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
		$Nc2JournalBlock = $this->getNc2Model('journal_block');
		$nc2JournalBlocks = $Nc2JournalBlock->find('all');
		if (!$this->__saveNc3BlogFrameSettingFromNc2($nc2JournalBlocks)) {
			return false;
		}

		/* @var $nc2ToNc3Frame Nc2ToNc3Frame */
		/*
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Blog = ClassRegistry::init('Blogs.Blog');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		foreach ($nc2JournalBlocks as $nc2JournalBlock) {
			$nc2JournalBlockBlockld = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
			$nc3Frame = $Nc2ToNc3Frame->getMap($nc2JournalBlockBlockld);

			if (!$nc3Frame) {
				continue;
			}

			$nc3RoomId = $nc3Frame['Frame']['room_id'];
			$data = [
				'Blog' => [
					'status' => '1',
					'content' => $nc2Blog['Nc2Blog']['content']
				],
				'Block' => [
					'room_id' => $nc3RoomId,
					'plugin_key' => 'Blogs'
				],
				'Frame' => [
					'id' => $nc3Frame['Frame']['id']
				],
				'Topic' => [
					'plugin_key' => 'Blogs'
				]
			];

			//Blog テーブルの移行を実施
			//SAVE前にCurrentのデータを書き換えが必要なため
			Current::write('Plugin.key', 'Blogs');
			Current::write('Room.id', $nc3RoomId);

			CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

			$Blog->create();
			$Block->create();
			$Topic->create();

			if (!$Blog->saveBlog($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
				// ここでrollback
				$Blog->rollback();

				$message = $this->getLogArgument($nc2Blog) . "\n" .
					var_export($Blog->validationErrors, true);
				$this->writeMigrationLog($message);

				continue;
			}

			unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
			Current::remove('Room.id', $nc3RoomId);
			Current::remove('Plugin.key', 'Blogs');

		}
		*/
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Blog Migration end.'));
		return true;
	}

/**
 * Save JournalFrameSetting from Nc2.
 *
 * @param array $nc2JournalBlocks Nc2JournalBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3BlogFrameSettingFromNc2($nc2JournalBlocks) {

		$this->writeMigrationLog(__d('nc2_to_nc3', '  BlogFrameSetting data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$BlogFrameSetting = ClassRegistry::init('Blogs.BlogFrameSetting');
		foreach ($nc2JournalBlocks as $nc2JournalBlock) {
			$BlogFrameSetting->begin();
			try {
				$data = $this->generateNc3FrameSettingData($nc2JournalBlock);
				if (!$data) {
					continue;
				}

				if (!$BlogFrameSetting->saveBlogFrameSetting($data)) {
					var_dump('shippai');exit;
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalBlocks) . "\n" .
						var_export($BlogFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

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
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$BlogFrameSetting->saveBlogFrameSetting($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  BlogFrameSetting data Migration end.'));

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

