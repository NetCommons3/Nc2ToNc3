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
 * Nc2 all members room id.
 */
	const NC2_ALL_MEMBERS_ROOM_ID = '0';

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
		if ($nc2RoomId === self::NC2_ALL_MEMBERS_ROOM_ID) {
			$roomMap['Room'] = [
				'id' => Space::getRoomIdRoot(Space::COMMUNITY_SPACE_ID),
				'space_id' => Space::COMMUNITY_SPACE_ID,
			];
		}

		if (!$roomMap) {
			$message
				= __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarManage));
			$this->_writeMigrationLog($message);
			return [];
		}

		// プライベートスペースのデータは移行できない
		$nc3SpaceId = $roomMap['Room']['space_id'];
		if ($nc3SpaceId == Space::PRIVATE_SPACE_ID) {
			$message
				= __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarManage));
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
		$nc3RolesRoom
			= $RolesRoom->findByRoomIdAndRoleKey($nc3RoomId, 'general_user', 'RolesRoom.id', null, -1);

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
			$message
				= __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarBlock));
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

		// rm2.calendar_frame_settings.display_typeは、rm1.calendar_block.display_type -1で登録
		// rm1.calendar_block.display_type=1の場合、rm2.calendar_frame_settings.display_type=2(月表示(拡大))で登録
		$nc3DisplayType = (string)((int)$nc2CalendarBlock['Nc2CalendarBlock']['display_type'] - 1);
		if ($nc3DisplayType === '0') {
			$nc3DisplayType = (string)CalendarsComponent::CALENDAR_DISP_TYPE_LARGE_MONTHLY;
		}
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		if (empty($nc2CalendarBlock['Nc2CalendarBlock']['display_count'])) {
			$nc2CalendarBlock['Nc2CalendarBlock']['display_count'] = 1;
		}
		// NC3で無効なstart_pos値(2:1月表示、3:4月表示)は0で登録
		$nc3StartPos = $nc2CalendarBlock['Nc2CalendarBlock']['start_pos'];
		if ($nc3StartPos == 2 || $nc3StartPos == 3) {
			$nc3StartPos = 0;
		}

		$data = [
			'display_type' => $nc3DisplayType,
			'start_pos' => $nc3StartPos,
			'display_count' => $nc2CalendarBlock['Nc2CalendarBlock']['display_count'],
			'is_myroom' => $nc2CalendarBlock['Nc2CalendarBlock']['myroom_flag'],
			'is_select_room' => $nc2CalendarBlock['Nc2CalendarBlock']['select_room'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CalendarBlock['Nc2CalendarBlock']),
			'created' => $this->_convertDate($nc2CalendarBlock['Nc2CalendarBlock']['insert_time']),
		];

		/* @var $CalendarFrameSetting CalendarFrameSetting */
		$CalendarFrameSetting = ClassRegistry::init('Calendars.CalendarFrameSetting');
		$nc3CalendarSetting
			= $CalendarFrameSetting->findByFrameKey($frameMap['Frame']['key'], null, null, -1);

		$data['CalendarFrameSetting'] = $data + $nc3CalendarSetting['CalendarFrameSetting'];
		$nc3CalendarRooms
			= $this->__generateNc3CalendarSelectRoomData($nc2CalendarBlock, $nc3CalendarSetting);
		$data['CalendarFrameSettingSelectRoom'] = $nc3CalendarRooms;

		return $data;
	}

/**
 * Generate Nc3CalendarActionPlan data.
 *
 * Data sample
 * data[CalendarActionPlan][origin_event_id]:0
 * data[CalendarActionPlan][origin_event_key]:
 * data[CalendarActionPlan][origin_rrule_id]:0
 * data[CalendarActionPlan][origin_rrule_key]:
 * data[CalendarActionPlan][is_detail]:1
 * data[CalendarActionPlan][title_icon]:
 * data[CalendarActionPlan][title]:Title
 * data[CalendarActionPlan][enable_time]:0
 * data[CalendarActionPlan][detail_start_datetime]:2017-03-07 00:00
 * data[CalendarActionPlan][detail_end_datetime]:2017-03-07 00:00
 * data[CalendarActionPlan][is_repeat]:0
 * data[CalendarActionPlan][repeat_freq]:DAILY
 * data[CalendarActionPlan][rrule_interval][DAILY]:1
 * data[CalendarActionPlan][rrule_interval][WEEKLY]:1
 * data[CalendarActionPlan][rrule_interval][MONTHLY]:1
 * data[CalendarActionPlan][rrule_interval][YEARLY]:1
 * data[CalendarActionPlan][rrule_byday][WEEKLY]:
 * data[CalendarActionPlan][rrule_byday][WEEKLY][]:TU
 * data[CalendarActionPlan][rrule_byday][MONTHLY]:
 * data[CalendarActionPlan][rrule_bymonthday][MONTHLY]:
 * data[CalendarActionPlan][rrule_bymonth][YEARLY]:
 * data[CalendarActionPlan][rrule_bymonth][YEARLY][]:3
 * data[CalendarActionPlan][rrule_byday][YEARLY]:
 * data[CalendarActionPlan][rrule_term]:COUNT
 * data[CalendarActionPlan][rrule_count]:3
 * data[CalendarActionPlan][rrule_until]:2017-03-07
 * data[CalendarActionPlan][plan_room_id]:1
 * data[CalendarActionPlan][location]:
 * data[CalendarActionPlan][contact]:
 * data[CalendarActionPlan][description]:
 * data[CalendarActionPlan][timezone_offset]:Asia/Tokyo
 * data[CalendarActionPlan][enable_email]:
 *
 * 以下移行時には不要と思われる。
 * data[CalendarActionPlan][origin_event_recurrence]:0
 * data[CalendarActionPlan][origin_event_exception]:0
 * data[CalendarActionPlan][origin_num_of_event_siblings]:0
 * data[CalendarActionPlan][first_sib_event_id]:0
 * data[CalendarActionPlan][first_sib_year]:2017
 * data[CalendarActionPlan][first_sib_month]:3
 * data[CalendarActionPlan][first_sib_day]:7
 * data[CalendarActionPlan][easy_start_date]:
 * data[CalendarActionPlan][easy_hour_minute_from]:
 * data[CalendarActionPlan][easy_hour_minute_to]:
 * data[CalendarActionPlan][email_send_timing]:5
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2CalendarPlan Nc2CalendarPlan data.
 * @return array Nc3CalendarFrameSetting data.
 */
	public function generateNc3CalendarActionPlanData(Model $model, $nc2CalendarPlan) {
		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$nc2RoomId = $nc2CalendarPlan['Nc2CalendarPlan']['room_id'];

		$roomMap = $Nc2ToNc3Room->getMap($nc2RoomId);
		if ($nc2RoomId === self::NC2_ALL_MEMBERS_ROOM_ID) {
			$roomMap['Room'] = [
				'id' => Space::getRoomIdRoot(Space::COMMUNITY_SPACE_ID),
				'space_id' => Space::COMMUNITY_SPACE_ID,
			];
		}

		if (!$roomMap) {
			$message
				= __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CalendarPlan));
			$this->_writeMigrationLog($message);
			return [];
		}

		$data = [];

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2CalendarId = $nc2CalendarPlan['Nc2CalendarPlan']['calendar_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CalendarActionPlan', $nc2CalendarId);
		if ($mapIdList) {
			// 移行済み
			return [];

			/* @var $CalendarEvent CalendarEvent */
			/* 既存データを更新する場合
			$CalendarEvent = ClassRegistry::init('Calendars.CalendarEvent');
			$nc3Event = $CalendarEvent->findById($mapIdList[$nc2CalendarId], 'key', null, -1);
			$nc3Event = $CalendarEvent->getEventByKey($nc3Event['CalendarEvent']['key']);
			// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/View/Elements/CalendarPlans/detail_edit_hiddens.ctp#L44-L50
			$data += [
				'origin_event_id' => $nc3Event['CalendarEvent']['id'],
				'origin_event_key' => $nc3Event['CalendarEvent']['key'],
				'origin_rrule_id' => $nc3Event['CalendarRrule']['id'],
				'origin_rrule_key' => $nc3Event['CalendarRrule']['key'],
			];
			*/
		}

		/* @var $Nc2CalendarPDetail AppModel */
		$Nc2CalendarPDetail = $this->_getNc2Model('calendar_plan_details');
		$nc2PlanId = $nc2CalendarPlan['Nc2CalendarPlan']['plan_id'];
		$nc2CalendarPDetail = $Nc2CalendarPDetail->findByPlanId($nc2PlanId, null, null, -1);

		$nc2TimezoneOffset = $nc2CalendarPlan['Nc2CalendarPlan']['timezone_offset'];
		$nc2StartTimeFull = $this->_convertDate($nc2CalendarPlan['Nc2CalendarPlan']['start_time_full']);
		$nc2EndTimeFull = $this->_convertDate($nc2CalendarPlan['Nc2CalendarPlan']['end_time_full']);
		$nc2AllDayFlag = $nc2CalendarPlan['Nc2CalendarPlan']['allday_flag'];
		$dateFormat = 'Y-m-d H:i';
		if ($nc2AllDayFlag) {
			$dateFormat = 'Y-m-d';
		}

		$nc3TimezoneOffset = $this->_convertTimezone($nc2TimezoneOffset);
		if ($nc3TimezoneOffset === 'UTC') {
			$nc3TimezoneOffset = 'Etc/Greenwich';
		}
		if ($nc3TimezoneOffset === 'Europe/Brussels') {
			$nc3TimezoneOffset = 'Europe/Amsterdam';
		}
		if ($nc3TimezoneOffset === 'Pacific/Honolulu') {
			$nc3TimezoneOffset = 'US/Hawaii';
		}
		if ($nc3TimezoneOffset === 'America/Chicago') {
			$nc3TimezoneOffset = 'US/Central';
		}
		if ($nc3TimezoneOffset === 'Asia/Vladivostok') {
			$nc3TimezoneOffset = 'Australia/Brisbane';
		}
		if ($nc3TimezoneOffset === 'America/New_York') {
			$nc3TimezoneOffset = 'US/Eastern';
		}

		// サマータイムが適用されている場合に予定の開始、終了時刻がずれないために、$nc2TimezoneOffsetを調整
		$dateTimeZone = new DateTimeZone($nc3TimezoneOffset);
		$timeZoneOffset = $dateTimeZone->getOffset(new DateTime("now", $dateTimeZone));
		$nc2TimezoneOffset = $timeZoneOffset / 3600;

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data += [
			'origin_event_id' => null,
			// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/View/Elements/CalendarPlans/detail_edit_hiddens.ctp#L132
			// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarActionPlan.php#L466-L470
			// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarActionPlan.php#L376-L377
			'is_detail' => '1',
			'title_icon' => $this->_convertTitleIcon($nc2CalendarPlan['Nc2CalendarPlan']['title_icon']),
			'title' => $nc2CalendarPlan['Nc2CalendarPlan']['title'],
			'enable_time' => !$nc2AllDayFlag,
			'detail_start_datetime' => date(
				$dateFormat,
				strtotime($nc2StartTimeFull) + ($nc2TimezoneOffset * 3600)
			),
			'detail_end_datetime' => date(
				$dateFormat,
				strtotime($nc2EndTimeFull) + ($nc2TimezoneOffset * 3600)
			),
			'timezone_offset' => $nc3TimezoneOffset,
			'plan_room_id' => $roomMap['Room']['id'],
			'location' => $nc2CalendarPDetail['Nc2CalendarPlanDetail']['location'],
			'contact' => $nc2CalendarPDetail['Nc2CalendarPlanDetail']['contact'],
			'description' => $model->convertWYSIWYG(
				$nc2CalendarPDetail['Nc2CalendarPlanDetail']['description']
			),
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CalendarPlan['Nc2CalendarPlan']),
			'created' => $this->_convertDate($nc2CalendarPlan['Nc2CalendarPlan']['insert_time']),
			// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/View/Elements/CalendarPlans/detail_edit_mail.ctp#L20-L21
			'enable_email' => false,
			'email_send_timing' => '5',
			// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L129
			'status' => '1',
			'is_repeat' => false,
		];

		$data['CalendarActionPlan']
			= $this->__generateNc3RRuleData($nc2CalendarPlan, $nc2CalendarPDetail, $data);
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarActionPlan.php#L549
		$data['WorkflowComment'] = null;

		return $data;
	}

/**
 * Generate Nc3CalendarActionPlan data.
 *
 * @param array $nc2CalendarPlan Nc2CalendarPlan data.
 * @param array $nc2CalendarPDetail Nc2CalendarPlanDetail data.
 * @param array $nc3ActionPlan Nc3CalendarActionPlan data.
 * @return array Nc3RRule data.
 */
	private function __generateNc3RRuleData($nc2CalendarPlan, $nc2CalendarPDetail, $nc3ActionPlan) {
		// 繰り返しデータとして移行すると、繰り返し予定のなかで、削除された予定も改めて登録されてしまうため、
		// 繰り返しでの登録はしない方が良い。
		// 改めて登録されても良いのであればっ場合でも、登録処理後に、繰り返し予定分のmapデータを作成しないと繰り返し数×繰り返し数分の予定を作成してしまう。
		/*
		$nc2RRule = $nc2CalendarPDetail['Nc2CalendarPlanDetail']['rrule'];
		if ($nc2RRule) {
			$nc3ActionPlan['is_repeat'] = true;

			$nc2RRules = [];
			foreach (explode(';', $nc2RRule) as $nc2RRuleValue) {
				list($key, $value) = explode('=', $nc2RRuleValue);
				$nc2RRules[$key] = $value;
			}

			$frequency = $nc2RRules['FREQ'];
			$nc3ActionPlan += [
				'is_repeat' => true,
				'repeat_freq' => $frequency,
				'rrule_interval' => [
					$frequency => $nc2RRules['INTERVAL']
				],
				'rrule_byday' => null,
				'rrule_bymonthday' => null,
				'rrule_bymonth' => null,
			];

			if (isset($nc2RRules['BYDAY'])) {
				if ($frequency == 'WEEKLY') {
					$nc2RRules['BYDAY'] = explode(',', $nc2RRules['BYDAY']);
				}

				$nc3ActionPlan['rrule_byday'] = [
					$frequency => $nc2RRules['BYDAY']
				];
			}
			if (isset($nc2RRules['BYMONTHDAY'])) {
				$nc3ActionPlan['rrule_bymonthday'] = [
					$frequency => $nc2RRules['BYMONTHDAY']
				];
			}
			if (isset($nc2RRules['BYMONTH'])) {
				$nc3ActionPlan['rrule_bymonth'] = [
					$frequency => explode(',', $nc2RRules['BYMONTH'])
				];
			}

			if (isset($nc2RRules['COUNT'])) {
				$nc3ActionPlan += [
					'rrule_term' => 'COUNT',
					'rrule_count' => $nc2RRules['COUNT']
				];
			}
			if (isset($nc2RRules['UNTIL'])) {
				$nc2TimezoneOffset = $nc2CalendarPlan['Nc2CalendarPlan']['timezone_offset'];
				$nc3ActionPlan += [
					'rrule_term' => 'UNTIL',
					'rrule_until' => date('Y-m-d', strtotime($nc2RRules['UNTIL']) + ($nc2TimezoneOffset * 3600))
				];
			}
		}
		*/

		return $nc3ActionPlan;
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
		/* @var $CalendarEvent CalendarEvent */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$CalendarEvent = ClassRegistry::init('Calendars.CalendarEvent');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('CalendarActionPlan', $nc2CalendarIds);
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
