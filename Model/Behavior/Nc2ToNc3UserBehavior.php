<?php
/**
 * Nc2ToNc3UserBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3UserBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserBehavior
 *
 */
class Nc2ToNc3UserBehavior extends Nc2ToNc3UserBaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2User Nc2User data.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2User) {
		return $this->__getLogArgument($nc2User);
	}

/**
 * Return whether it is waiting for approval.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2User Nc2User data.
 * @return bool True if data is waiting for approval.
 */
	public function isApprovalWaiting(Model $model, $nc2User) {
		return $this->__isApprovalWaiting($nc2User);
	}

/**
 * Check migration target
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2User Nc2User data.
 * @return bool True if data is migration target.
 */
	public function isMigrationRow(Model $model, $nc2User) {
		// 承認待ち、本人確認待ちは移行しない（通知した承認用URLが違うため）
		// 移行して再度通知した方が良い気もする
		// とりあえず移行しとく
		if ($this->__isApprovalWaiting($nc2User)) {
			$message = __d('nc2_to_nc3', '%s is not active.Resend approval mail.', $this->__getLogArgument($nc2User));
			$this->_writeMigrationLog($message);
			return true;
		}

		return true;
	}

/**
 * Save existing map
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Users Nc2User data
 * @return void
 */
	public function saveExistingMap(Model $model, $nc2Users) {
		$idList = Hash::combine($nc2Users, '{n}.Nc2User.login_id', '{n}.Nc2User.user_id');

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');
		$query = [
			'fields' => [
				'User.id',
				'User.username',
			],
			'conditions' => [
				'User.username' => array_keys($idList)
			],
			'recursive' => -1
		];
		$nc3Users = $User->find('all', $query);

		foreach ($nc3Users as $nc3User) {
			$username = $nc3User['User']['username'];
			$nc2UserId = $idList[$username];
			$idMap = [
				$nc2UserId => $nc3User['User']['id']
			];
			$this->_saveMap('User', $idMap);
		}
	}

/**
 * Convert fixed field
 *
 * @param Model $model Model using this behavior
 * @param string $nc2Field Nc2User field name.
 * @param array $nc3User Nc3User data.
 * @param array $nc2User Nc2User data.
 * @return string convert data.
 */
	public function convertFixedField(Model $model, $nc2Field, $nc3User, $nc2User) {
		$nc2UserValue = $nc2User['Nc2User'][$nc2Field];

		if ($nc2Field == 'role_authority_id') {
			/* @var $Nc2ToNc3UserRole Nc2ToNc3UserRole */
			$Nc2ToNc3UserRole = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserRole');
			$userRole = $Nc2ToNc3UserRole->getMap($nc2UserValue);

			return $userRole['UserRoleSetting']['role_key'];
		}

		if ($nc2Field == 'lang_dirname') {
			switch ($nc2UserValue) {
				case 'japanese':
					$code = 'ja';
					break;

				case 'english':
					$code = 'en';
					break;

				default:
					$code = 'auto';

			}

			return $code;
		}

		if ($nc2Field == 'timezone_offset') {
			$timezoneMap = [
				'-12.0' => 'Pacific/Kwajalein',
				'-11.0' => 'Pacific/Midway',
				'-10.0' => 'Pacific/Honolulu',
				'-9.0' => 'America/Anchorage',
				'-8.0' => 'America/Los_Angeles',
				'-7.0' => 'America/Denver',
				'-6.0' => 'America/Chicago',
				'-5.0' => 'America/New_York',
				'-4.0' => 'America/Dominica',
				'-3.5' => 'America/St_Johns',
				'-3.0' => 'America/Argentina/Buenos_Aires',
				'-2.0' => 'Atlantic/South_Georgia',
				'-1.0' => 'Atlantic/Azores',
				'0.0' => 'UTC',
				'1.0' => 'Europe/Brussels',
				'2.0' => 'Europe/Athens',
				'3.0' => 'Asia/Baghdad',
				'3.5' => 'Asia/Tehran',
				'4.0' => 'Asia/Muscat',
				'4.5' => 'Asia/Kabul',
				'5.0' => 'Asia/Karachi',
				'5.5' => 'Asia/Kolkata',
				'6.0' => 'Asia/Dhaka',
				'7.0' => 'Asia/Bangkok',
				'8.0' => 'Asia/Singapore',
				'9.0' => 'Asia/Tokyo',
				'9.5' => 'Australia/Darwin',
				'10.0' => 'Asia/Vladivostok',
				'11.0' => 'Australia/Sydney',
				'12.0' => 'Asia/Kamchatka'
			];

			return Hash::get($timezoneMap, [$nc2UserValue], 'Asia/Tokyo');
		}
	}

/**
 * GetNc2ItemContent
 *
 * @param Model $model Model using this behavior
 * @param string $nc2ItemId Nc2Item item_id.
 * @param array $nc2UserItemLink Nc2UsersItemsLink data
 * @return string Nc2UsersItemsLink.content.
 */
	public function getNc2ItemContent(Model $model, $nc2ItemId, $nc2UserItemLink) {
		$path = '{n}.Nc2UsersItemsLink[item_id=' . $nc2ItemId . '].content';
		$nc2ItemContent = Hash::extract($nc2UserItemLink, $path);
		if (!$nc2ItemContent) {
			return '';
		}

		return $nc2ItemContent[0];
	}

/**
 * GetNc2ItemContent
 *
 * @param Model $model Model using this behavior
 * @param string $dataTypeKey Nc3UserAttributeSetting data_type_key.
 * @param string $nc2Content Nc2UsersItemsLink.content.
 * @param array $nc3Choices Nc3UserAttributeChoice data.
 * @return string Nc3UserAttributeChoice.code.
 */
	public function getChoiceCode(Model $model, $dataTypeKey, $nc2Content, $nc3Choices) {
		$nc2Contents = explode('|', $nc2Content);
		$choiceCodes = [];
		foreach ($nc2Contents as $nc2Choice) {
			if ($nc2Choice === '') {
				$path = '{n}[code=no_setting]';
				$nc3Choice = Hash::extract($nc3Choices, $path);
				if ($nc3Choice) {
					$choiceCodes[] = $nc3Choice[0]['code'];
				}

				continue;
			}

			$path = '{n}[name=' . $nc2Choice . ']';
			$nc3Choice = Hash::extract($nc3Choices, $path);
			if ($nc3Choice) {
				$choiceCodes[] = $nc3Choice[0]['code'];

				continue;
			}

		}

		if ($dataTypeKey != 'checkbox') {
			return Hash::get($choiceCodes, ['0']);
		}

		return $choiceCodes;
	}

/**
 * Get Nc2PagesUsersLink data.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2User Nc3User data.
 * @return array Nc2PagesUsersLink list.
 */
	public function getNc2PagesUsersLinkByUserId(Model $model, $nc2User) {
		/* @var $Nc2PagesUsersLink AppModel */
		$Nc2PagesUsersLink = $this->_getNc2Model('pages_users_link');

		$query = [
			'conditions' => [
				'Nc2PagesUsersLink.user_id' => $nc2User['Nc2User']['user_id'],
			],
			'recursive' => -1
		];

		return $Nc2PagesUsersLink->find('all', $query);
	}

/**
 * Get Nc3RolesRoomsUser list.
 * [Nc3RolesRoomsUser.room_id => Nc3RolesRoomsUser.id]
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3User Nc3Room data.
 * @param array $roomMap Room map data.
 * @return array Nc3RolesRoomsUser list.
 */
	public function getNc3RolesRoomsUserListByUserIdAndRoomId(Model $model, $nc3User, $roomMap) {
		$nc3UserId = Hash::get($nc3User, ['User', 'id']);
		if (!$nc3UserId) {
			return [];
		}

		/* @var $RolesRoomsUser RolesRoomsUser */
		$RolesRoomsUser = ClassRegistry::init('Rooms.RolesRoomsUser');
		$query = [
			'fields' => [
				'RolesRoomsUser.room_id',
				'RolesRoomsUser.id'
			],
			'conditions' => [
				'RolesRoomsUser.user_id' => $nc3UserId,
				'RolesRoomsUser.room_id' => Hash::extract($roomMap, '{n}.Room.id')
			],
			'recursive' => -1
		];

		return $RolesRoomsUser->find('list', $query);
	}

/**
 * Get Nc3RoleRoom list.
 * [RolesRoom.room_id => [Nc3RolesRoom.role_key => Nc3RolesRoom.id]]
 *
 * @param Model $model Model using this behavior.
 * @param array $roomMap Room map data.
 * @return array Nc3RoleRoom list.
 */
	public function getNc3RoleRoomListByRoomId(Model $model, $roomMap) {
		/* @var $RolesRoom RolesRoom */
		$RolesRoom = ClassRegistry::init('Rooms.RolesRoom');
		$query = [
			'fields' => [
				'RolesRoom.role_key',
				'RolesRoom.id',
				'RolesRoom.room_id'
			],
			'conditions' => [
				'RolesRoom.room_id' => Hash::extract($roomMap, '{n}.Room.id')
			],
			'recursive' => -1
		];

		return $RolesRoom->find('list', $query);
	}

/**
 * Get Nc3RoleRoom id.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3RoleRoomList Nc3RoleRoom list.
 * @param string $nc3RoomId Nc2Room id.
 * @param string $nc2RoleAuthotityId Nc2PagesUsersLink role_authority_id.
 * @return array Nc2PagesUsersLink list.
 */
	public function getNc3RoleRoomIdByNc2RoleAuthotityId(Model $model, $nc3RoleRoomList, $nc3RoomId, $nc2RoleAuthotityId) {
		/* @var $Nc2ToNc3RoomRole Nc2ToNc3RoomRole */
		$Nc2ToNc3RoomRole = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3RoomRole');
		$roomRoleMap = $Nc2ToNc3RoomRole->getMap($nc2RoleAuthotityId);
		$nc3RoleKey = $roomRoleMap['RolesRoom']['role_key'];

		return $nc3RoleRoomList[$nc3RoomId][$nc3RoleKey];
	}

/**
 * Get Log argument.
 *
 * @param array $nc2User Nc2User data
 * @return string Log argument
 */
	private function __getLogArgument($nc2User) {
		return 'Nc2User ' .
			'user_id:' . $nc2User['Nc2User']['user_id'] .
			'handle:' . $nc2User['Nc2User']['handle'];
	}

/**
 * Return whether it is waiting for approval.
 *
 * @param array $nc2User Nc2User data.
 * @return bool True if data is waiting for approval.
 */
	private function __isApprovalWaiting($nc2User) {
		$active = $nc2User['Nc2User']['active_flag'];
		$isApprovalWaiting = !in_array($active, ['0', '1']);

		return $isApprovalWaiting;
	}

}
