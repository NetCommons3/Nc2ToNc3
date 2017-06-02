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
 * @method array generateNc3LinkBlockData($frameMap, $nc2Linklist)
 * @method array generateNc3LinkData($frameMap, $nc2Linklist)
 * @method array generateNc3LinkFrameSettingData($frameMap, $nc2LinklistBlock)
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
		if (!$this->__saveLinkBlockFromNc2($nc2Linklists)) {
			return false;
		}

		/* @var $Nc2LinklistLink AppModel */
		$Nc2LinklistLink = $this->getNc2Model('linklist_link');
		$query['order'] = [
			'linklist_id',
			'link_sequence'
		];
		$nc2LinklistLinks = $Nc2LinklistLink->find('all', $query);
		if (!$this->__saveLinkFromNc2($nc2LinklistLinks)) {
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
 * Save LinkBlock from Nc2.
 *
 * @param array $nc2Linklists Nc2Linklist data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveLinkBlockFromNc2($nc2Linklists) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Link data Migration start.'));

		/* @var $LinkBlock LinkBlock */
		/* @var $Nc2LinklistBlock AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $BlocksLanguage BlocksLanguage */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$LinkBlock = ClassRegistry::init('Links.LinkBlock');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$LinkBlock->Behaviors->Block->settings = $LinkBlock->actsAs['Blocks.Block'];

		$Nc2LinklistBlock = $this->getNc2Model('linklist_block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		foreach ($nc2Linklists as $nc2Linklist) {
			$LinkBlock->begin();
			try {
				$nc2RoomId = $nc2Linklist['Nc2Linklist']['room_id'];
				$nc2LinklistBlock = $Nc2LinklistBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2LinklistBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Linklist));
					$this->writeMigrationLog($message);
					$LinkBlock->rollback();
					continue;
				}

				$frameMap = $Nc2ToNc3Frame->getMap($nc2LinklistBlock['Nc2LinklistBlock']['block_id']);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Linklist));
					$this->writeMigrationLog($message);
					$LinkBlock->rollback();
					continue;
				}

				$data = $this->generateNc3LinkBlockData($frameMap, $nc2Linklist);
				if (!$data) {
					$LinkBlock->rollback();
					continue;
				}

				$query = [
					'conditions' => [
						'linklist_id' => $nc2Linklist['Nc2Linklist']['linklist_id']
					],
					'order' => 'category_sequence',
				];
				$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('linklist_category', $query);
				$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);

				$this->writeCurrent($frameMap, 'links');
				$LinkBlock->create();
				$BlocksLanguage->create();
				if (!$LinkBlock->saveLinkBlock($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$LinkBlock->rollback();
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Linklist) . "\n" .
						var_export($LinkBlock->validationErrors, true);
					$this->writeMigrationLog($message);
					$LinkBlock->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2LinklistId = $nc2Linklist['Nc2Linklist']['linklist_id'];
				$idMap = [
					$nc2LinklistId => $LinkBlock->data['Block']['id'],
				];
				$this->saveMap('LinkBlock', $idMap);

				// Model::save を オーバーライドしているため、Model::id はセットされない。
				// その代わり、Model::data はクリアされない。
				// @see https://github.com/NetCommons3/Blocks/blob/3.1.0/Model/BlockBaseModel.php#L55
				// @see https://github.com/cakephp/cakephp/blob/2.9.8/lib/Cake/Model/Model.php#L1979
				if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $LinkBlock->data['Block']['id'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Linklist);
					$this->writeMigrationLog($message);

					$LinkBlock->rollback();
					continue;
				}

				$LinkBlock->commit();

			} catch (Exception $ex) {
				$LinkBlock->rollback($ex);
				throw $ex;
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Link data Migration end.'));

		return true;
	}

/**
 * Save Link from Nc2.
 *
 * @param array $nc2LinklistLinks Nc2LinklistLink data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveLinkFromNc2($nc2LinklistLinks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Link data Migration start.'));

		/* @var $Link Link */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Link = ClassRegistry::init('Links.Link');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

		Current::write('Plugin.key', 'links');
		foreach ($nc2LinklistLinks as $nc2LinklistLink) {
			$Link->begin();
			try {
				$data = $this->generateNc3LinkData($nc2LinklistLink);
				if (!$data) {
					$Link->rollback();
					continue;
				}

				$nc3BlockId = $data['Link']['block_id'];
				$nc2CategoryId = $nc2LinklistLink['Nc2LinklistLink']['category_id'];
				$nc3Category = $Nc2ToNc3Category->getNc3Category($nc3BlockId, $nc2CategoryId);
				if ($nc3Category) {
					$data['Link']['category_id'] = $nc3Category['Category']['id'];
					$data['LinkOrder']['category_key'] = $nc3Category['Category']['key'];
				}

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block.id', $nc3BlockId);

				$nc2RoomId = $nc2LinklistLink['Nc2LinklistLink']['room_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomId);
				$nc3RoomId = $mapIdList[$nc2RoomId];
				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// 一応Model::validatの初期化
				$Link->validate = [];

				if (!$Link->saveLink($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Link->rollback();

					$message = $this->getLogArgument($nc2LinklistLink) . "\n" .
							var_export($Link->validationErrors, true);
					$this->writeMigrationLog($message);

					$Link->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2LinkId = $nc2LinklistLink['Nc2LinklistLink']['link_id'];
				$idMap = [
					$nc2LinkId => $Link->id,
				];
				$this->saveMap('Link', $idMap);

				$Link->commit();

			} catch (Exception $ex) {
				$Link->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Block.id');
		Current::remove('Room.id');
		Current::remove('Plugin.key');

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
		$LinkFrameSetting = ClassRegistry::init('Links.LinkFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2LinklistBlocks as $nc2LinklistBlock) {
			$LinkFrameSetting->begin();
			try {
				$nc2BlockId = $nc2LinklistBlock['Nc2LinklistBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2LinklistBlock));
					$this->writeMigrationLog($message);
					$LinkFrameSetting->rollback();
					continue;
				}

				$data = $this->generateNc3LinkFrameSettingData($frameMap, $nc2LinklistBlock);
				if (!$data) {
					$LinkFrameSetting->rollback();
					continue;
				}

				if (!$LinkFrameSetting->saveLinkFrameSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$LinkFrameSetting->rollback();

					$message = $this->getLogArgument($nc2LinklistBlock) . "\n" .
						var_export($LinkFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$LinkFrameSetting->rollback();
					continue;
				}

				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。 var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2LinklistBlock) . "\n" .
						var_export($Frame->validationErrors, true);
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

		$this->writeMigrationLog(__d('nc2_to_nc3', '  LinkFrameSetting data Migration end.'));

		return true;
	}
}

