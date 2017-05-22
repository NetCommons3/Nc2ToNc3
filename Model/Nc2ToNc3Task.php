<?php
/**
 * Nc2ToNc3Task
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Task
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
 * @see Nc2ToNc3TaskBehavior
 * @method string getLogArgument($nc2TaskBlock)
 * @method array generateNc3TaskData($frameMap, $nc2TodoData)
 * @method array generateNc3TaskContentsData($frameMap, $nc3Task, $nc2Task)
 *
 */
class Nc2ToNc3Task extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Task'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Task Migration start.'));

		/* @var $Nc2TodoModel AppModel */
		$Nc2TodoModel = $this->getNc2Model('todo');
		$nc2TodoDatas = $Nc2TodoModel->find('all');
		if (!$this->__saveTaskFromNc2($nc2TodoDatas)) {
			return false;
		}

		/* @var $Nc2TodoTask AppModel */
		$Nc2TodoTask = $this->getNc2Model('todo_task');
		$query['order'] = [
			'todo_id',
			'task_sequence'
		];
		$nc2TodoTasks = $Nc2TodoTask->find('all', $query);
		if (!$this->__saveTaskContentFromNc2($nc2TodoTasks)) {
			return false;
		}

		/* @var $Nc2TodoBlock AppModel */
		$Nc2TodoBlock = $this->getNc2Model('todo_block');
		$nc2TodoBlocks = $Nc2TodoBlock->find('all');
		if (!$this->__saveFrameFromNc2($nc2TodoBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Task Migration end.'));

		return true;
	}

/**
 * Save Task from Nc2.
 *
 * @param array $nc2TodoDatas Nc2Task data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveTaskFromNc2($nc2TodoDatas) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Task data Migration start.'));

		/* @var $Task Task */
		/* @var $Nc2TodoBlock AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Task = ClassRegistry::init('Tasks.Task');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Task->Behaviors->Block->settings = $Task->actsAs['Blocks.Block'];

		$Nc2TodoBlock = $this->getNc2Model('todo_block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		foreach ($nc2TodoDatas as $nc2TodoData) {
			$Task->begin();
			try {
				$nc2RoomId = $nc2TodoData['Nc2Todo']['room_id'];
				$nc2TodoBlock = $Nc2TodoBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2TodoBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2TodoData));
					$this->writeMigrationLog($message);
					$Task->rollback();
					continue;
				}

				$frameMap = $Nc2ToNc3Frame->getMap($nc2TodoBlock['Nc2TodoBlock']['block_id']);
				$data = $this->generateNc3TaskData($frameMap, $nc2TodoData);
				if (!$data) {
					$Task->rollback();
					continue;
				}

				$query['conditions'] = [
					'todo_id' => $nc2TodoData['Nc2Todo']['todo_id']
				];
				$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('todo_category', $query);
				$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);

				$this->writeCurrent($frameMap, 'tasks');

				$Task->create(false);
				$Block->create();
				$BlocksLanguage->create();
				if (!$Task->saveTask($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Task->rollback();
					$message = $this->getLogArgument($nc2TodoData) . "\n" .
						var_export($Task->validationErrors, true);
					$this->writeMigrationLog($message);
					$Task->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2TodoId = $nc2TodoData['Nc2Todo']['todo_id'];
				$idMap = [
					$nc2TodoId => $Task->id,
				];
				$this->saveMap('Task', $idMap);

				$nc3Task = $Task->findById($Task->id, 'block_id', null, -1);
				if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $nc3Task['Task']['block_id'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2TodoData);
					$this->writeMigrationLog($message);

					$Task->rollback();
					continue;
				}

				$Task->commit();

			} catch (Exception $ex) {
				$Task->rollback($ex);
				throw $ex;
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Task data Migration end.'));

		return true;
	}

/**
 * Save TaskContent from Nc2.
 *
 * @param array $nc2TodoTasks Nc2TodoTask data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveTaskContentFromNc2($nc2TodoTasks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  TaskContent data Migration start.'));

		/* @var $TaskContent TaskContent */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$TaskContent = ClassRegistry::init('Tasks.TaskContent');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

		Current::write('Plugin.key', 'tasks');
		foreach ($nc2TodoTasks as $nc2TodoTask) {
			$TaskContent->begin();
			try {
				$data = $this->generateNc3TaskContentData($nc2TodoTask);
				if (!$data) {
					$TaskContent->rollback();
					continue;
				}

				$nc3BlockId = $data['TaskContent']['block_id'];
				$nc2CategoryId = $nc2TodoTask['Nc2TodoTask']['category_id'];
				$data['TaskContent']['category_id'] = $Nc2ToNc3Category->getNc3CategoryId($nc3BlockId, $nc2CategoryId);

				$nc2RoomId = $nc2TodoTask['Nc2TodoTask']['room_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomId);
				$nc3RoomId = $mapIdList[$nc2RoomId];
				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// 一応Model::validatの初期化
				$TaskContent->validate = [];

				if (!$TaskContent->saveContent($data)) {
					$message = $this->getLogArgument($nc2TodoTask) . "\n" .
						var_export($TaskContent->validationErrors, true);
					$this->writeMigrationLog($message);

					$TaskContent->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2TaskId = $nc2TodoTask['Nc2TodoTask']['task_id'];
				$idMap = [
					$nc2TaskId => $TaskContent->id,
				];
				$this->saveMap('TaskContent', $idMap);

				$TaskContent->commit();

			} catch (Exception $ex) {
				$TaskContent->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  TaskContent data Migration end.'));

		return true;
	}

/**
 * Save Frame from Nc2.
 *
 * @param array $nc2TodoBlocks Nc2TodoBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveFrameFromNc2($nc2TodoBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Frame data Migration start.'));

		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Task Task */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Task = ClassRegistry::init('Tasks.Task');
		foreach ($nc2TodoBlocks as $nc2TodoBlock) {
			$Frame->begin();
			try {
				$nc2BlockId = $nc2TodoBlock['Nc2TodoBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = $this->getLogArgument($nc2TodoBlocks) . "\n" .
						var_export($Frame->validationErrors, true);
					$this->writeMigrationLog($message);

					$Frame->rollback();
					continue;
				}

				// TaskFrameModelは存在しないが、移行済みデータのためにDummyModel名で取得
				$mapIdList = $Nc2ToNc3Map->getMapIdList('TaskFrame', $nc2BlockId);
				if ($mapIdList) {
					$Frame->rollback();	// 移行済み
					continue;
				}

				$nc2TodoId = $nc2TodoBlock['Nc2TodoBlock']['todo_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Task', $nc2TodoId);
				$nc3TaskId = Hash::get($mapIdList, [$nc2TodoId]);
				$nc3Task = $Task->findById($nc3TaskId, ['block_id'], null, -1);
				if (!$nc3Task) {
					$Frame->rollback();	// ブロックデータなし
					continue;
				}

				$data['Frame'] = [
					'id' => $frameMap['Frame']['id'],
					'plugin_key' => 'tasks',
					'block_id' => $nc3Task['Task']['block_id'],
				];
				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。 var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2TodoBlocks) . "\n" .
						var_export($Frame->validationErrors, true);
					$this->writeMigrationLog($message);

					$Frame->rollback();
					continue;
				}

				$idMap = [
					$nc2BlockId => $frameMap['Frame']['id'],
				];
				// TaskFrameModelは存在しないが、移行済みデータのためにDummyModel名で登録
				$this->saveMap('TaskFrame', $idMap);

				$Frame->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Frame->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Frame data Migration end.'));

		return true;
	}

}

