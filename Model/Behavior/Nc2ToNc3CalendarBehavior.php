<?php
/**
 * Nc2ToNc3CalendarBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3CalendarBehavior
 *
 */
class Nc2ToNc3CalendarBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Calendar Array data of Nc2CalendarManage, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Calendar) {
		return $this->__getLogArgument($nc2Calendar);
	}

/**
 * Generate Nc3CalendarPermission data.
 *
 * Data sample
 * data[2][1][BlockRolePermission][content_creatable][general_user][id]:
 * data[2][1][BlockRolePermission][content_creatable][general_user][roles_room_id]:
 * data[2][1][BlockRolePermission][content_creatable][general_user][block_key]:
 * data[2][1][BlockRolePermission][content_creatable][general_user][permission]:content_creatable
 * data[2][1][BlockRolePermission][content_creatable][general_user][value]:
 * data[2][1][Calendar][block_key]:
 * data[2][1][Calendar][id]:
 * data[2][1][Calendar][use_workflow]:
 *
 * 1次元目のkey:space_id(使ってないっぽい),2次元目のkey:room_id
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2CalendarManage Nc2CalendarManage data.
 * @return array Nc3CalendarPermission data.
 */
	public function generateNc3CalendarPermissionData(Model $model, $nc2CalendarManage) {
		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$nc2RoomId = $nc2CalendarManage['Nc2CalendarManage']['room_id'];
		$roomMap = $Nc2ToNc3Room->getMap($nc2RoomId);
		if (!$roomMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarManage));
			$this->_writeMigrationLog($message);
			return [];
		}

		// プライベートスペースのデータは移行できない
		$nc3SpaceId = $roomMap['Room']['space_id'];
		if ($nc3SpaceId == Space::PRIVATE_SPACE_ID) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarManage));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CalendarPermission', $nc2RoomId);
		if ($mapIdList) {
			// 移行済み
			//return [];
		}

		/* @var $RolesRoom RolesRoom */
		$RolesRoom = ClassRegistry::init('Rooms.RolesRoom');
		$nc3RoomId = $roomMap['Room']['id'];
		$nc3RolesRoom = $RolesRoom->findByRoomIdAndRoleKey($nc3RoomId, 'general_user', 'RolesRoom.id', null, -1);

		/* @var $Block Block */
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Block = $Block->findByRoomIdAndPluginKey($nc3RoomId, 'calendars', 'Block.key', null, -1);
		$nc3BlockPermission = [];
		if ($nc3Block) {
			$nc3BlockKey = $nc3Block['Block']['key'];
			/* @var $BlockRolePermission BlockRolePermission */
			$BlockRolePermission = ClassRegistry::init('Blocks.BlockRolePermission');
			$query = [
				'conditions' => [
					'BlockRolePermission.block_key' => $nc3BlockKey,
					'BlockRolePermission.permission' => 'content_creatable',
					'RolesRoom.room_id' => $nc3RoomId,
					'RolesRoom.role_key' => 'general_user',
				],
				'recursive' => 0
			];
			$nc3BlockPermission = $BlockRolePermission->find('first', $query);
		}
		if (!$nc3BlockPermission) {
			$nc3BlockPermission['BlockRolePermission'] = [];
		}

		$nc3PermissionValue = '0';
		if ($nc2CalendarManage['Nc2CalendarManage']['add_authority_id'] == '2') {
			$nc3PermissionValue = '1';
		}
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc3PermissionData = [
			'roles_room_id' => $nc3RolesRoom['RolesRoom']['id'],
			'permission' => 'content_creatable',
			'value' => $nc3PermissionValue,
		];

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc3CreateData = [
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CalendarManage['Nc2CalendarManage']),
			'created' => $this->_convertDate($nc2CalendarManage['Nc2CalendarManage']['insert_time']),
		];

		// 1次元目のkey:space_idは使ってないっぽい
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarPermission.php#L301-L305
		$data[$nc3SpaceId][$nc3RoomId]['BlockRolePermission']['content_creatable']['general_user'] =
			$nc3PermissionData + $nc3CreateData + $nc3BlockPermission['BlockRolePermission'];
		$data[$nc3SpaceId][$nc3RoomId]['Calendar'] = $nc3CreateData;

		return $data;
	}

/**
 * Generate Nc3CalendarFrameSetting data.
 *
 * Data sample
 * data[CalendarFrameSetting][id]:
 * data[CalendarFrameSetting][frame_key]:
 * data[CalendarFrameSetting][room_id]:1
 * data[CalendarFrameSetting][is_myroom]:0
 * data[CalendarFrameSetting][display_type]:2
 * data[CalendarFrameSetting][is_select_room]:0
 * data[CalendarFrameSettingSelectRoom][1][room_id]:
 * data[CalendarFrameSettingSelectRoom][1][calendar_frame_setting_id]:1
 * data[CalendarFrameSettingSelectRoom][2][room_id]:
 * data[CalendarFrameSettingSelectRoom][2][calendar_frame_setting_id]:1
 * data[CalendarFrameSetting][start_pos]:0
 * data[CalendarFrameSetting][display_count]:3
 * data[CalendarFrameSetting][timeline_base_time]:8
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2CalendarBlock Nc2CalendarBlock data.
 * @return array Nc3CalendarFrameSetting data.
 */
	public function generateNc3CalendarFrameSettingData(Model $model, $nc2CalendarBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2CalendarBlock['Nc2CalendarBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CalendarFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'display_type' => (string)((int)$nc2CalendarBlock['Nc2CalendarBlock']['display_type'] - 1),
			'start_pos' => $nc2CalendarBlock['Nc2CalendarBlock']['start_pos'],
			'display_count' => $nc2CalendarBlock['Nc2CalendarBlock']['display_count'],
			'is_myroom' => $nc2CalendarBlock['Nc2CalendarBlock']['myroom_flag'],
			'is_select_room' => $nc2CalendarBlock['Nc2CalendarBlock']['select_room'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CalendarBlock['Nc2CalendarBlock']),
			'created' => $this->_convertDate($nc2CalendarBlock['Nc2CalendarBlock']['insert_time']),
		];

		/* @var $CalendarFrameSetting CalendarFrameSetting */
		$CalendarFrameSetting = ClassRegistry::init('Calendars.CalendarFrameSetting');
		$nc3CalendarSetting = $CalendarFrameSetting->findByFrameKey($frameMap['Frame']['key'], null, null, -1);

		$data['CalendarFrameSetting'] = $data + $nc3CalendarSetting['CalendarFrameSetting'];
		$nc3CalendarRooms = $this->__generateNc3CalendarSelectRoomData($nc2CalendarBlock, $nc3CalendarSetting);
		$data['CalendarFrameSettingSelectRoom'] = $nc3CalendarRooms;

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Calendar Array data of Nc2CalendarManage, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Calendar) {
		if (isset($nc2Calendar['Nc2CalendarManage'])) {
			return 'Nc2CalendarManage ' .
				'room_id:' . $nc2Calendar['Nc2CalendarManage']['room_id'];
		}

		if (isset($nc2Calendar['Nc2CalendarBlock'])) {
			return 'Nc2CalendarBlock ' .
				'block_id:' . $nc2Calendar['Nc2CalendarBlock']['block_id'];
		}

		return 'Nc2CalendarPlan ' .
			'calendar_id:' . $nc2Calendar['Nc2CalendarPlan']['calendar_id'] . ',' .
			'title:' . $nc2Calendar['Nc2CalendarPlan']['title'];
	}

/**
 * Get map
 *
 * @param array|string $nc2CalendarIds Nc2CalendarPlan calendar_id.
 * @return array Map data with Nc2Block block_id as key.
 */
	protected function _getMap($nc2CalendarIds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Frame Frame */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$CalendarEvent = ClassRegistry::init('Calendars.CalendarEvent');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Frame', $nc2CalendarIds);
		$query = [
			'fields' => [
				'CalendarEvent.id',
			],
			'conditions' => [
				'CalendarEvent.id' => $mapIdList
			],
			'recursive' => -1
		];
		$calendarEvents = $CalendarEvent->find('all', $query);
		if (!$calendarEvents) {
			return $calendarEvents;
		}

		$map = [];
		foreach ($calendarEvents as $calendarEvent) {
			$nc2Id = array_search($calendarEvent['CalendarEvent']['id'], $mapIdList);
			$map[$nc2Id] = $calendarEvent;
		}

		if (is_string($nc2CalendarIds)) {
			$map = $map[$nc2CalendarIds];
		}

		return $map;
	}

/**
 * Generate Nc3CalendarFrameSettingSelectRoom data.
 *
 * @param string $nc2CalendarBlock Nc2CalendarBlock data.
 * @param string $nc3CalendarSetting Nc3CalendarFrameSetting data.
 * @return array Nc3CalendarFrameSettingSelectRoom data.
 */
	private function __generateNc3CalendarSelectRoomData($nc2CalendarBlock, $nc3CalendarSetting) {
		/* @var $Nc2CalendarRoom AppModel */
		$Nc2CalendarRoom = $this->_getNc2Model('calendar_select_room');
		$query = [
			'fields' => [
				'Nc2CalendarSelectRoom.room_id',
				'Nc2CalendarSelectRoom.room_id',
			],
			'conditions' => [
				'Nc2CalendarSelectRoom.block_id' => $nc2CalendarBlock['Nc2CalendarBlock']['block_id'],
			],
			'recursive' => -1
		];
		$nc2RoomIdList = $Nc2CalendarRoom->find('list', $query);

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomIdList);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];
		foreach ($nc2RoomIdList as $nc2RoomId) {
			$data[] = [
				'calendar_frame_setting_id' => $nc3CalendarSetting['CalendarFrameSetting']['id'],
				'room_id' => $mapIdList[$nc2RoomId],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CalendarBlock['Nc2CalendarBlock']),
				'created' => $this->_convertDate($nc2CalendarBlock['Nc2CalendarBlock']['insert_time']),
			];
		}

		return $data;
	}

}
