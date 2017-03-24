<?php
/**
 * Nc2ToNc3Link
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Link
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
 * @see Nc2ToNc3LinkBehavior
 * @method string getLogArgument($nc2LinkBlock)
 * @method array generateNc3LinkData($frameMap, $nc2Linklist, $nc2Categories)
 * @method array generateNc3LinkBlockData($frameMap, $nc2Linklist, $nc2Categories)
 * @method array generateNc3LinkFrameSettingData($nc2LinklistBlock)
 *
 */
class Nc2ToNc3Link extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Link'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Link Migration start.'));

		/* @var $Nc2Linklist AppModel */
		$Nc2Linklist = $this->getNc2Model('linklist');
		$nc2Linklists = $Nc2Linklist->find('all');
		if (!$this->__saveLinkFromNc2($nc2Linklists)) {
			return false;
		}

		/* @var $Nc2LinklistBlock AppModel */
		$Nc2LinklistBlock = $this->getNc2Model('linklist_block');
		$nc2LinklistBlocks = $Nc2LinklistBlock->find('all');

		if (!$this->__saveLinkFrameSettingFromNc2($nc2LinklistBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Link Migration end.'));

		return true;
	}

/**
 * Save Link from Nc2.
 *
 * @param array $nc2Linklists Nc2Linklist data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveLinkFromNc2($nc2Linklists) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Link data Migration start.'));

		/* @var $Link Link */
		/* @var $LinkBlock LinkBlock */
		/* @var $Nc2LinklistBlock AppModel */
		/* @var $Nc2LinklistCategory AppModel */
		/* @var $Nc2LinklistLink AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		$Link = ClassRegistry::init('Links.Link');
		$LinkBlock = ClassRegistry::init('Links.LinkBlock');
		$Nc2LinklistBlock = $this->getNc2Model('linklist_block');
		$Nc2LinklistCategory = $this->getNc2Model('linklist_category');
		$Nc2LinklistLink = $this->getNc2Model('linklist_link');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2Linklists as $nc2Linklist) {
			$LinkBlock->begin();
			try {
				$nc2LinklistId = $nc2Linklist['Nc2Linklist']['linklist_id'];
				$nc2Categories = $Nc2LinklistCategory->findAllByLinklistId($nc2LinklistId, null, ['category_sequence' => 'ASC'], -1);

				$nc2RoomId = $nc2Linklist['Nc2Linklist']['room_id'];
				$nc2LinklistBlock = $Nc2LinklistBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2LinklistBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Linklist));
					$this->writeMigrationLog($message);
					$LinkBlock->rollback();
					continue;
				}
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L577-L578
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L631-L634
				$frameMap = $Nc2ToNc3Frame->getMap($nc2LinklistBlock['Nc2LinklistBlock']['block_id']);
				$nc3RoomId = $frameMap['Frame']['room_id'];

				$data = $this->generateNc3LinkBlockData($frameMap, $nc2Linklist, $nc2Categories);
				if (!$data) {
					$LinkBlock->rollback();
					continue;
				}

				Current::write('Frame.key', $frameMap['Frame']['key']);
				Current::write('Frame.room_id', $nc3RoomId);
				Current::write('Frame.plugin_key', 'links');

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
				Current::write('Plugin.key', 'links');

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// Model::idを初期化しないとUpdateになってしまう。
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L442
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireSetting.php#L129-L149
				$Frame->create();

				if (!$LinkBlock->saveLinkBlock($data)) {
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Linklist) . "\n" .
						var_export($LinkBlock->validationErrors, true);
					$this->writeMigrationLog($message);

					$LinkBlock->rollback();
					continue;
				}

				$nc2Links = $Nc2LinklistLink->findAllByLinklistId($nc2LinklistId, null, ['link_sequence' => 'ASC'], -1);
				foreach ($nc2Links as $nc2Link) {
					$data = $this->generateNc3LinkData($LinkBlock->data, $nc2Link, $nc2Categories);
					if (!$Link->saveLink($data)) {
						// @see https://phpmd.org/rules/design.html
						$message = $this->getLogArgument($nc2Link) . "\n" .
							var_export($Link->validationErrors, true);
						$this->writeMigrationLog($message);

						$Link->rollback();
						continue;
					}
				}

				// 登録処理で使用しているデータを空に戻す
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$idMap = [
					$nc2LinklistId => $LinkBlock->id,
				];
				$this->saveMap('Link', $idMap);

				$LinkBlock->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $LinkBlockFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$LinkBlock->rollback($ex);
				throw $ex;
			}
		}

		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Plugin.key');
		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Link data Migration end.'));

		return true;
	}

/**
 * Save Link from Nc2.
 *
 * @param array $nc2LinklistBlocks Nc2Linklist data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveLinkFrameSettingFromNc2($nc2LinklistBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  LinkFrameSetting data Migration start.'));

		/* @var $LinkFrameSetting LinkFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Block Block */
		$LinkFrameSetting = ClassRegistry::init('Links.LinkFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		foreach ($nc2LinklistBlocks as $nc2LinklistBlock) {
			$LinkFrameSetting->begin();
			try {
				$data = $this->generateNc3LinkFrameSettingData($nc2LinklistBlock);
				if (!$data) {
					$LinkFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2LinklistBlock['Nc2LinklistBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2LinklistBlock));
					$this->writeMigrationLog($message);
					$LinkFrameSetting->rollback();
					continue;
				}
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireFrameDisplayQuestionnaire.php#L221
				Current::write('Frame.key', $frameMap['Frame']['key']);

				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireFrameSetting.php#L165-L167
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L464
				$nc3Block = $Block->findByRoomIdAndPluginKey(
					$frameMap['Frame']['room_id'],
					'links',
					'id',
					null,
					-1
				);
				Current::write('Block.id', $nc3Block['Block']['id']);

				if (!$LinkFrameSetting->saveLinkFrameSetting($data)) {
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2LinklistBlock) . "\n" .
						var_export($LinkFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$LinkFrameSetting->rollback();
					continue;
				}

				$idMap = [
					$nc2BlockId => $LinkFrameSetting->id,
				];
				$this->saveMap('LinkFrameSetting', $idMap);

				$LinkFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$LinkFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  LinkFrameSetting data Migration end.'));

		return true;
	}
}

