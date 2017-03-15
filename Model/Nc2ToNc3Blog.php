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

		if (!$this->__saveNc3BlogFromNc2($nc2JournalBlocks)) {
			return false;
		}

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

	private function __saveNc3BlogFromNc2($nc2JournalBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$Blog = ClassRegistry::init('Blogs.Blog');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Blog->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Blog->Behaviors->Block->settings = $Blog->actsAs['Blocks.Block'];

		$Nc2Journal = $this->getNc2Model('journal');

		foreach ($nc2JournalBlocks as $nc2JournalBlock) {
			$nc2Journal = $Nc2Journal->findByJournalId($nc2JournalBlock['Nc2JournalBlock']['journal_id'], null, null, -1);

			$Blog->begin();
			try {
				$data = $this->generateNc3BlogData($nc2JournalBlock, $nc2Journal);
				if (!$data) {
					$Blog->rollback();
					continue;
				}
				if (!$Blog->saveBlog($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2JournalBlock) . "\n" .
						var_export($Blog->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $Blog->id
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
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog data Migration end.'));
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