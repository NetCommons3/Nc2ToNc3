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
 * @method array generateNc3TaskData($frameMap, $nc2Todo)
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

		/* @var $Nc2Todo AppModel */
		$Nc2Todo = $this->getNc2Model('todo');
		$nc2Todos = $Nc2Todo->find('all');
		if (!$this->__saveTaskFromNc2($nc2Todos)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Task Migration end.'));

		return true;
	}

/**
 * Save Task from Nc2.
 *
 * @param array $nc2Tasks Nc2Task data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveTaskFromNc2($nc2Todos) {
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
		foreach ($nc2Todos as $nc2Todo) {
			$Task->begin();
			try {
				$nc2RoomId = $nc2Todo['Nc2Todo']['room_id'];
				$nc2TodoBlock = $Nc2TodoBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2TodoBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Todo));
					$this->writeMigrationLog($message);
					$Task->rollback();
					continue;
				}

				$frameMap = $Nc2ToNc3Frame->getMap($nc2TodoBlock['Nc2TodoBlock']['block_id']);

				$data = $this->generateNc3TaskData($frameMap, $nc2Todo);
				if (!$data) {
					$Task->rollback();
					continue;
				}

				$this->__writeCurrent($frameMap, 'tasks');

				$Task->create();
				$Block->create();
				$BlocksLanguage->create();
				if (!$Task->saveTask($data)) {
					$message = $this->getLogArgument($nc2Todo) . "\n" .
						var_export($Task->validationErrors, true);
					$this->writeMigrationLog($message);

					$Task->rollback();
					continue;
				}

				$nc2TodoId = $nc2Todo['Nc2Todo']['todo_id'];
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

		$this->__removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Task data Migration end.'));

		return true;
	}

/**
 * Write Current.
 *
 * @param array $frameMap array data.
 * @param string $pluginKey plugin key.
 * @return void
 * @throws Exception
 */
	private function __writeCurrent($frameMap, $pluginKey) {
		$nc3RoomId = $frameMap['Frame']['room_id'];
		Current::write('Frame.key', $frameMap['Frame']['key']);
		Current::write('Frame.room_id', $frameMap['Frame']['room_id']);
		Current::write('Frame.plugin_key', $pluginKey);

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
		Current::write('Plugin.key', $pluginKey);

		// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
		Current::write('Room.id', $nc3RoomId);
		CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;
	}

/**
 * Remove Current.
 *
 * @return void
 * @throws Exception
 */
	private function __removeUseCurrent() {
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Plugin.key');
		Current::remove('Room.id');
	}
}

