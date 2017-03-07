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
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2CalendarBlock));
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
				'room_id:' . $nc2Block['Nc2CalendarManage']['room_id'];
		}

		if (isset($nc2Calendar['Nc2CalendarBlock'])) {
			return 'Nc2CalendarBlock ' .
				'block_id:' . $nc2Block['Nc2CalendarBlock']['block_id'];
		}

		return 'Nc2CalendarPlan ' .
			'calendar_id:' . $nc2Block['Nc2CalendarPlan']['calendar_id'] . ',' .
			'title:' . $nc2Block['Nc2CalendarPlan']['title'];
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
