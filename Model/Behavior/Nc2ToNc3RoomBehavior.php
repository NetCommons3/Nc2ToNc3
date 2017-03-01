<?php
/**
 * Nc2ToNc3RoomBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3RoomBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3RoomBehavior
 *
 */
class Nc2ToNc3RoomBehavior extends Nc2ToNc3RoomBaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Page Nc2Page data.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Page) {
		return $this->__getLogArgument($nc2Page);
	}

/**
 * Get Nc2Room conditions.
 *
 * @param Model $model Model using this behavior.
 * @return array Nc2Room conditions.
 */
	public function getNc2RoomConditions(Model $model) {
		return $this->__getNc2RoomConditions();
	}

/**
 * Get other laguage Nc3Room id.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Page Nc2Page data.
 * @return array other laguage Nc3Room id.
 */
	public function getNc2OtherLaguageRoomIdList(Model $model, $nc2Page) {
		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->_getNc2Model('pages');
		$conditions = $this->__getNc2RoomConditions();
		$conditions += [
			'Nc2Page.lang_dirname !=' => $nc2Page['Nc2Page']['lang_dirname'],
			'Nc2Page.permalink' => $nc2Page['Nc2Page']['permalink'],
		];
		$query = [
			'fields' => [
				'Nc2Page.page_id',
				'Nc2Page.room_id'
			],
			'conditions' => $conditions,
			'recursive' => -1,
			'callbacks' => false
		];
		$nc2OtherLaguageList = $Nc2Page->find('list', $query);

		return $nc2OtherLaguageList;
	}

/**
 * Check migration target.
 *
 * @param Model $model Model using this behavior.
 * @param array $userMap User map data.
 * @param string $nc2UserId Nc2User id.
 * @param array $nc2Page Nc2Page data.
 * @param array $nc3RoleRoomUserList Nc3RolesRoomsUser id array.
 * @return bool True if data is migration target.
 */
	public function isNc2PagesUsersLinkToBeMigrationed(Model $model, $userMap, $nc2UserId, $nc2Page, $nc3RoleRoomUserList) {
		// 対応するNc3User.idがなければ移行しない
		if (!isset($userMap[$nc2UserId])) {
			return false;
		}

		// Nc3RolesRoomsUser.idがない場合は移行する(新規作成)
		$nc3UserId = $userMap[$nc2UserId]['User']['id'];
		$nc3RolesRoomsUserId = Hash::get($nc3RoleRoomUserList, [$nc3UserId]);
		if (!$nc3RolesRoomsUserId) {
			return true;
		}

		if (!$nc2Page['Nc2Page']['lang_dirname']) {
			return true;
		}

		// オリジナルの言語のNc2PagesUsersLinkデータは移行する
		// オリジナルの言語ではない場合は、Nc3RolesRoomsUserデータを更新しない
		//   →オリジナルの言語のNc2PagesUsersLinkデータを優先する
		$nc3LaguageId = $this->_convertLanguage($nc2Page['Nc2Page']['lang_dirname']);
		$isOriginLaguage = ($nc3LaguageId == $this->_getLanguageIdFromNc2());
		if ($isOriginLaguage) {
			return true;
		}

		return false;
	}

/**
 * Get Nc2PagesUsersLink data.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3Room Nc3Room data.
 * @param array $nc2Page Nc2Page data.
 * @return array Nc2PagesUsersLink list.
 */
	public function getNc2PagesUsersLinkByRoomId(Model $model, $nc3Room, $nc2Page) {
		/* @var $Nc2PagesUsersLink AppModel */
		$Nc2PagesUsersLink = $this->_getNc2Model('pages_users_link');

		$conditions = [
			'Nc2PagesUsersLink.room_id' => $nc2Page['Nc2Page']['room_id'],
		];
		if ($nc3Room['Room']['default_participation']) {
			$defaultEntryRoleAuth = $this->_getNc2DefaultEntryRoleAuth($nc2Page['Nc2Page']['space_type']);
			$conditions += [
				'Nc2PagesUsersLink.role_authority_id !=' => $defaultEntryRoleAuth,
			];
		}

		$query = [
			'conditions' => $conditions,
			'recursive' => -1
		];

		return $Nc2PagesUsersLink->find('all', $query);
	}

/**
 * Get Nc3RolesRoomsUser list.
 * [Nc3RolesRoomsUser.user_id => Nc3RolesRoomsUser.id]
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3Room Nc3Room data.
 * @param array $userMap User map data.
 * @return array Nc3RolesRoomsUser list.
 */
	public function getNc3RolesRoomsUserListByRoomIdAndUserId(Model $model, $nc3Room, $userMap) {
		/* @var $RolesRoomsUser RolesRoomsUser */
		$RolesRoomsUser = ClassRegistry::init('Rooms.RolesRoomsUser');
		$query = [
			'fields' => [
				'RolesRoomsUser.user_id',
				'RolesRoomsUser.id'
			],
			'conditions' => [
				'RolesRoomsUser.user_id' => Hash::extract($userMap, '{s}.User.id'),
				'RolesRoomsUser.room_id' => $nc3Room['Room']['id']
			],
			'recursive' => -1
		];

		return $RolesRoomsUser->find('list', $query);
	}

/**
 * Get Nc3RoleRoom list.
 * [Nc3RolesRoom.role_key => Nc3RolesRoom.id]
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3Room Nc3Room data.
 * @return array Nc3RoleRoom list.
 */
	public function getNc3RoleRoomListByRoomId(Model $model, $nc3Room) {
		/* @var $RolesRoom RolesRoom */
		$RolesRoom = ClassRegistry::init('Rooms.RolesRoom');
		$query = [
			'fields' => [
				'RolesRoom.role_key',
				'RolesRoom.id'
			],
			'conditions' => [
				'RolesRoom.room_id' => $nc3Room['Room']['id']
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
 * @param string $nc2RoleAuthotityId Nc2PagesUsersLink role_authority_id.
 * @return array Nc2PagesUsersLink list.
 */
	public function getNc3RoleRoomIdByNc2RoleAuthotityId(Model $model, $nc3RoleRoomList, $nc2RoleAuthotityId) {
		/* @var $Nc2ToNc3RoomRole Nc2ToNc3RoomRole */
		$Nc2ToNc3RoomRole = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3RoomRole');
		$roomRoleMap = $Nc2ToNc3RoomRole->getMap($nc2RoleAuthotityId);
		$nc3RoleKey = $roomRoleMap['RolesRoom']['role_key'];

		return $nc3RoleRoomList[$nc3RoleKey];
	}

/**
 * Save existing map
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Pages Nc2Page data
 * @return void
 */
	public function saveExistingMap(Model $model, $nc2Pages) {
		// パブリックルームのmapデータ作成。Nc2のパブリックルーム取得条件が良く分からない。とりあえずの条件で取得
		// パブリックルーム以外の対応するルームが既存の処理について、対応させるデータが名前くらいしかない気がする。。。名前でマージして良いのか微妙なので保留

		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->_getNc2Model('pages');
		$nc2Page = $Nc2Page->findByRootIdAndSpaceType(
			'0',
			'1',
			[
				'Nc2Page.page_id',
				'Nc2Page.room_id',
			],
			null,
			-1
		);
		$nc2Id = $nc2Page['Nc2Page']['room_id'];

		/* @var $Room Room */
		$Room = ClassRegistry::init('Rooms.Room');
		$spaces = $Room->getSpaces();
		$nc3RoomId = $spaces[Space::PUBLIC_SPACE_ID]['Space']['room_id_root'];

		$idMap = [
			$nc2Id => $nc3RoomId
		];
		$this->_saveMap('Room', $idMap);

		// パブリックルームの先頭ページmapデータ作成。Nc3Page.idを1で対応付け
		//　@see https://github.com/NetCommons3/Pages/blob/3.1.0/Config/Migration/1472409223_records.php#L47
		$nc2Id = $nc2Page['Nc2Page']['page_id'];
		$idMap = [
			$nc2Id => '1'
		];
		$this->_saveMap('Page', $idMap);
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Page Nc2Page data
 * @return string Log argument
 */
	private function __getLogArgument($nc2Page) {
		return 'Nc2Page ' .
			'page_id:' . $nc2Page['Nc2Page']['page_id'] .
			'page_name:' . $nc2Page['Nc2Page']['page_name'];
	}

/**
 * Get Nc2Room conditions.
 *
 * @return array Nc2Room conditions.
 */
	private function __getNc2RoomConditions() {
		$conditions = [
			'Nc2Page.page_id = Nc2Page.room_id',
			'Nc2Page.private_flag' => '0',
			'Nc2Page.root_id !=' => '0',
		];

		return $conditions;
	}

}
