<?php
/**
 * Nc2ToNc3TaskBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3TaskBehavior
 *
 */
class Nc2ToNc3TaskBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Task Array data of Nc2Task.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Task) {
		return $this->__getLogArgument($nc2Task);
	}

/**
 * Generate Nc3TaskBlock data.
 *
 * Data sample
 * data[Frame][id]:
 * data[Block][id]:
 * data[Block][key]:
 * data[Block][room_id]:
 * data[Block][plugin_key]:links
 * data[Block][name]:
 * data[Block][public_type]:1
 * data[Task][key]:
 * data[Task][name]:
 * data[Task][created_user]:
 * data[Task][created]:
 * data[TaskSetting][use_workflow]:0
 * data[TaskSetting][use_comment_approval]:0
 * data[TaskSetting][use_comment]:0
 * data[Categories]:
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap FrameMap data.
 * @param array $nc2TodoData Nc2TodoData data.
 * @return array Nc3Task data.
 */
	public function generateNc3TaskData(Model $model, $frameMap, $nc2TodoData) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Task', $nc2TodoData['Nc2Todo']['todo_id']);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		if (!$frameMap) {
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
		];
		$data['Block'] = [
			'id' => '',
			'key' => '',
			'room_id' => $frameMap['Frame']['room_id'],
			'plugin_key' => 'tasks',
			'public_type' => 1,
		];
		$data['Task'] = [
			'key' => '',
			'name' => $nc2TodoData['Nc2Todo']['todo_name'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2TodoData['Nc2Todo']),
			'created' => $this->_convertDate($nc2TodoData['Nc2Todo']['insert_time']),
		];
		$data['TaskSetting'] = [
			'use_workflow' => '0',
			'use_comment_approval' => '0',
			'use_comment' => '0',
		];

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
		$data['Topic'] = [
			'plugin_key' => 'tasks',
		];

		return $data;
	}

/**
 * Generate Nc3Task data.
 *
 * Data sample
 * data[TaskContent][id]:
 * data[TaskContent][key]:
 * data[TaskContent][block_id]:
 * data[TaskContent][status]:
 * data[TaskContent][language_id]:
 * data[TaskContent][category_id]:
 * data[TaskContent][progress_rate]:
 * data[TaskContent][title]:
 * data[TaskContent][priority]:
 * data[TaskContent][is_date_set]:
 * data[TaskContent][task_start_date]:
 * data[TaskContent][task_end_date]:
 * data[TaskContent][content]:
 * data[TaskContent][is_enable_mail]:
 * data[TaskContent][task_key]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2TodoTask Nc2TodoTask data.
 * @return array Nc3Task data.
 */
	public function generateNc3TaskContentData(Model $model, $nc2TodoTask) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2TaskId = $nc2TodoTask['Nc2TodoTask']['task_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('TaskContent', $nc2TaskId);
		if ($mapIdList) {
			return [];	// 移行済み
		}

		$nc2TodoId = $nc2TodoTask['Nc2TodoTask']['todo_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Task', $nc2TodoId);
		if (!$mapIdList) {
			return [];	// ブロックデータなし
		}
		$nc3TaskId = $mapIdList[$nc2TodoId];

		/* @var $Task Task */
		$Task = ClassRegistry::init('Tasks.Task');
		$nc3Task = $Task->findById($nc3TaskId, null, null);

		$taskEndDate = null;
		$isDateSet = '0';
		if ($nc2TodoTask['Nc2TodoTask']['period']) {
			$taskEndDate = date('Y-m-d H:i:s',
				strtotime($nc2TodoTask['Nc2TodoTask']['period'] . ' -1 second'));
			$isDateSet = '1';
		}
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'TaskContent' => [
				'id' => '',
				'key' => '',
				'block_id' => $nc3Task['Task']['block_id'],
				'status' => '1',
				'language_id' => $nc3Task['Task']['language_id'],
				'is_completion' => $nc2TodoTask['Nc2TodoTask']['state'],
				'progress_rate' => $nc2TodoTask['Nc2TodoTask']['progress'],
				'title' => $nc2TodoTask['Nc2TodoTask']['task_value'],
				'priority' => $this->_convertPriority($nc2TodoTask['Nc2TodoTask']['priority']),
				'is_date_set' => $isDateSet,
				'task_start_date' => null,
				'task_end_date' => $taskEndDate,
				'content' => '',
				'is_enable_mail' => '0',
				'task_key' => $nc3Task['Task']['key'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2TodoTask['Nc2TodoTask']),
				'created' => $this->_convertDate($nc2TodoTask['Nc2TodoTask']['insert_time']),
				// 新着用に更新日を移行
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L146
				'modified' => $this->_convertDate($nc2TodoTask['Nc2TodoTask']['update_time']),
			],
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Task Array data of Nc2Task.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Task) {
		if (isset($nc2Task['Nc2TodoBlock'])) {
			return 'Nc2TodoBlock ' .
				'block_id:' . $nc2Task['Nc2TodoBlock']['block_id'];
		}

		if (isset($nc2Task['Nc2TodoTask'])) {
			return 'Nc2TodoTask ' .
				'task_id:' . $nc2Task['Nc2TodoTask']['task_id'];
		}

		return 'Nc2Todo ' .
			'todo_id:' . $nc2Task['Nc2Todo']['todo_id'] . ',' .
			'todo_name:' . $nc2Task['Nc2Todo']['todo_name'];
	}

/**
 * Get priority map
 *
 * @param string $nc2Priority nc2TodoTask.priority data.
 * @return string data with TaskContents.priority.
 */
	protected function _convertPriority($nc2Priority) {
		// nc2 => nc3
		$map = [
			'0' => '1', // 低
			'1' => '2', // 中
			'2' => '3', // 高
		];

		return $map[$nc2Priority];
	}

}
