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
		/* @var $TaskContent TaskContent */
		/* @var $Nc2TodoBlock AppModel */
		/* @var $Nc2TodoTask AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Block Block */
		/* @var $BlocksLanguage BlocksLanguage */
		$Task = ClassRegistry::init('Tasks.Task');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Task->Behaviors->Block->settings = $Task->actsAs['Blocks.Block'];

		$TaskContent = ClassRegistry::init('Tasks.TaskContent');
		$Nc2TodoBlock = $this->getNc2Model('todo_block');
		$Nc2TodoTask = $this->getNc2Model('todo_task');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
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

				$nc2TodoId = $nc2TodoData['Nc2Todo']['todo_id'];
				$nc2Tasks = $Nc2TodoTask->findAllByTodoId($nc2TodoId, null, ['task_sequence' => 'ASC'], -1);
				$nc3Task = $Task->read();
				foreach ($nc2Tasks as $nc2Task) {
					$data = $this->generateNc3TaskContentsData($frameMap, $nc3Task, $nc2Task);
					if (!$data) {
						continue;
					}
					$TaskContent->validate = [];
					if (!$TaskContent->saveContent($data)) {
						$message = $this->getLogArgument($nc2Task) . "\n" .
							var_export($TaskContent->validationErrors, true);
						$this->writeMigrationLog($message);
						$Task->rollback();
						continue;
					}
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$idMap = [
					$nc2TodoId => $Task->id,
				];
				$this->saveMap('Task', $idMap);

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

