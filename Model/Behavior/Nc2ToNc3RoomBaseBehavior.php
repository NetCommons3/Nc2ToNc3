<?php
/**
 * Nc2ToNc3RoomBaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('DefaultRolePermission', 'Roles.Model');

/**
 * Nc2ToNc3RoomBaseBehavior
 *
 */
class Nc2ToNc3RoomBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Nc3Room default_role_key list from Nc2Config default_entry_role_auth_group.
 *
 * @var array
 */
	private $__nc3DefaultRoleKeyList = null;

/**
 * Nc3DefaultRolePermission data.
 *
 * @var array
 */
	private $__nc3DefaultRolePermission = null;

/**
 * Nc2DefaultEntryRoleAuth data.
 *
 * @var array
 */
	private $__nc2DefaultEntryRoleAuthList = null;

/**
 * Nc3Language data.
 *
 * @var array
 */
	private $__nc3CurrentLanguage = null;

/**
 * Get Nc3Room default_role_key by Nc2Page space_type.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2SpaceType Nc2Page space_type
 * @return string Nc3Room default_role_key from Nc2Config default_entry_role_auth_group.
 */
	public function getNc3DefaultRoleKeyByNc2SpaceType(Model $model, $nc2SpaceType) {
		return $this->_getNc3DefaultRoleKeyByNc2SpaceType($nc2SpaceType);
	}

/**
 * Get Nc3DefaultRolePermission data.
 *
 * @param Model $model Model using this behavior.
 * @return array Nc3DefaultRolePermission data.
 */
	public function getNc3DefaultRolePermission(Model $model) {
		return $this->_getNc3DefaultRolePermission();
	}

/**
 * Get nc2 DefaultEntryRoleAuth.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2SpaceType Nc2Page space_type.1:public,2:group
 * @return string Nc2 DefaultEntryRoleAuth
 */
	public function getNc2DefaultEntryRoleAuth(Model $model, $nc2SpaceType) {
		return $this->_getNc2DefaultEntryRoleAuth($nc2SpaceType);
	}

/**
 * Change nc3 current language data
 *
 * @param Model $model Model using this behavior.
 * @return void
 */
	public function changeNc3CurrentLanguage(Model $model) {
		$this->_changeNc3CurrentLanguage();
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	public function restoreNc3CurrentLanguage() {
		$this->_restoreNc3CurrentLanguage();
	}

/**
 * Get map
 *
 * @param array|string $nc2RoomIds Nc2Page room_id.
 * @return array Map data with Nc2Page room_id as key.
 */
	protected function _getMap($nc2RoomIds = null) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Room Room */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Room = ClassRegistry::init('Rooms.Room');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomIds);
		$query = [
			'fields' => [
				'Room.id'
			],
			'conditions' => [
				'Room.id' => $mapIdList
			],
			'recursive' => -1
		];
		$rooms = $Room->find('all', $query);
		if (!$rooms) {
			return $rooms;
		}

		$map = [];
		foreach ($rooms as $room) {
			$nc2Id = array_search($room['Room']['id'], $mapIdList);
			$map[$nc2Id] = $room;
		}

		if (is_string($nc2RoomIds)) {
			$map = $map[$nc2RoomIds];
		}

		return $map;
	}

/**
 * Get Nc3Room default_role_key by Nc2Page space_type.
 *
 * @param string $nc2SpaceType Nc2Page space_type.1:public,2:group
 * @return string Nc3Room default_role_key from Nc2Config default_entry_role_auth_group.
 */
	protected function _getNc3DefaultRoleKeyByNc2SpaceType($nc2SpaceType) {
		if (isset($this->__nc3DefaultRoleKeyList)) {
			return $this->__nc3DefaultRoleKeyList[$nc2SpaceType];
		}

		if (!$this->__nc2DefaultEntryRoleAuthList) {
			$this->__setNc2DefaultEntryRoleAuthList();
		}

		$authorityToRoleMap = [
			'4' => 'general_user',
			'5' => 'visitor',
		];

		$groupAuthorityId = $this->__nc2DefaultEntryRoleAuthList[Nc2ToNc3Room::NC2_SPACE_TYPE_PUBLIC];
		$publicAuthorityId = $this->__nc2DefaultEntryRoleAuthList[Nc2ToNc3Room::NC2_SPACE_TYPE_GROUP];
		// Nc2Page.space_typeをkeyにする。1:public,2:group
		$this->__nc3DefaultRoleKeyList = [
			Nc2ToNc3Room::NC2_SPACE_TYPE_PUBLIC => $authorityToRoleMap[$publicAuthorityId],
			Nc2ToNc3Room::NC2_SPACE_TYPE_GROUP => $authorityToRoleMap[$groupAuthorityId],
		];

		return $this->__nc3DefaultRoleKeyList[$nc2SpaceType];
	}

/**
 * Get Nc3DefaultRolePermission data.
 *
 * @return array Nc3DefaultRolePermission data.
 * @see https://github.com/NetCommons3/Rooms/blob/3.1.0/Controller/Component/RoomsRolesFormComponent.php#L115-L121
 * @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Controller/Component/WorkflowComponent.php#L230-L244
 */
	protected function _getNc3DefaultRolePermission() {
		if (isset($this->__nc3DefaultRolePermission)) {
			return $this->__nc3DefaultRolePermission;
		}

		/* @var $RolePermission DefaultRolePermission */
		$RolePermission = ClassRegistry::init('Roles.DefaultRolePermission');
		$query = [
			'fields' => [
				'DefaultRolePermission.*',
				'DefaultRolePermission.value AS default'
			],
			'conditions' => array(
				'DefaultRolePermission.type' => DefaultRolePermission::TYPE_ROOM_ROLE,
				'DefaultRolePermission.permission' => [
					'content_publishable',
					'html_not_limited'
				],
			),
			'recursive' => -1,
		];
		$data = $RolePermission->find('all', $query);
		$data['DefaultRolePermission'] = Hash::combine(
			$data,
			'{n}.DefaultRolePermission.role_key',
			'{n}.DefaultRolePermission',
			'{n}.DefaultRolePermission.permission'
		);
		$this->__nc3DefaultRolePermission = Hash::remove($data['DefaultRolePermission'], '{s}.{s}.id');

		return $this->__nc3DefaultRolePermission;
	}

/**
 * Get nc2 DefaultEntryRoleAuth.
 *
 * @param string $nc2SpaceType Nc2Page space_type.1:public,2:group
 * @return string Nc2 DefaultEntryRoleAuth
 */
	protected function _getNc2DefaultEntryRoleAuth($nc2SpaceType) {
		if (!$this->__nc2DefaultEntryRoleAuthList) {
			$this->__setNc2DefaultEntryRoleAuthList();
		}

		return $this->__nc2DefaultEntryRoleAuthList[$nc2SpaceType];
	}

/**
 * Change nc3 current language data
 *
 * @return void
 */
	protected function _changeNc3CurrentLanguage() {
		/* @var $Language Language */
		$Language = ClassRegistry::init('M17n.Language');

		$nc3LanguageId = $this->_getLanguageIdFromNc2();
		if (Current::read('Language.id') != $nc3LanguageId) {
			$this->__nc3CurrentLanguage = Current::read('Language');
			$language = $Language->findById($nc3LanguageId, null, null, -1);
			Current::write('Language', $language['Language']);
		}
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	protected function _restoreNc3CurrentLanguage() {
		if (isset($this->__nc3CurrentLanguage)) {
			Current::write('Language', $this->__nc3CurrentLanguage);
			unset($this->__nc3CurrentLanguage);
		}
	}

/**
 * Set nc2 DefaultEntryRoleAuth list.
 *
 * @return void
 */
	private function __setNc2DefaultEntryRoleAuthList() {
		/* @var $Nc2Config AppModel */
		$Nc2Config = $this->_getNc2Model('config');
		$query = [
			'fields' => [
				'Nc2Config.conf_name',
				'Nc2Config.conf_value',
			],
			'conditions' => [
				'Nc2Config.conf_name' => [
					'default_entry_role_auth_public',
					'default_entry_role_auth_group',
				]
			],
			'recursive' => -1
		];
		$defaultEntryList = $Nc2Config->find('list', $query);

		// Nc2Page.space_typeをkeyにする。1:public,2:group
		$this->__nc2DefaultEntryRoleAuthList = [
			Nc2ToNc3Room::NC2_SPACE_TYPE_PUBLIC => $defaultEntryList['default_entry_role_auth_public'],
			Nc2ToNc3Room::NC2_SPACE_TYPE_GROUP => $defaultEntryList['default_entry_role_auth_group'],
		];
	}

}
