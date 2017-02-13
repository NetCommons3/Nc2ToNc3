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
 * Nc2ToNc3UserBaseBehavior
 *
 */
class Nc2ToNc3RoomBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Nc3Room default_role_key list from Nc2Config default_entry_role_auth_group.
 *
 * @var array
 */
	private $__defaultRoleKeyListFromNc2 = null;

/**
 * Nc3DefaultRolePermission data.
 *
 * @var array
 */
	private $__nc3DefaultRolePermission = null;

/**
 * Get Nc3Room default_role_key from Nc2Config default_entry_role_auth_group.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2SpaceType Nc2Page space_type
 * @return string Nc3Room default_role_key from Nc2Config default_entry_role_auth_group.
 */
	public function getDefaultRoleKeyFromNc2(Model $model, $nc2SpaceType) {
		return $this->_getDefaultRoleKeyFromNc2($nc2SpaceType);
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
 * Get map
 *
 * @param array|string $nc2RoomIds Nc2Page room_id.
 * @return array Map data with Nc2Page room_id as key.
 */
	protected function _getMap($nc2RoomIds = null) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $User User */
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
 * Get Nc3Room default_role_key from Nc2Config default_entry_role_auth_group.
 *
 * @param string $nc2SpaceType Nc2Page space_type.1:public,2:group
 * @return string Nc3Room default_role_key from Nc2Config default_entry_role_auth_group.
 */
	protected function _getDefaultRoleKeyFromNc2($nc2SpaceType) {
		if (isset($this->__defaultRoleKeyListFromNc2)) {
			return $this->__defaultRoleKeyListFromNc2[$nc2SpaceType];
		}

		/* @var $Nc2Config AppModel */
		$Nc2Config = $this->_getNc2Model('config');
		$configData = $Nc2Config->findAllByConfName(
			[
				'default_entry_role_auth_group',
				'default_entry_role_auth_public',
			],
			'conf_value',
			'conf_name',
			null,
			null,
			-1
		);

		$authorityToRoleMap = [
			'4' => 'general_user',
			'5' => 'visitor',
		];

		$groupAuthorityId = $configData[0]['Nc2Config']['conf_value'];
		$publicAuthorityId = $configData[1]['Nc2Config']['conf_value'];
		$this->__defaultRoleKeyListFromNc2 = [
			'1' => $authorityToRoleMap[$publicAuthorityId],
			'2' => $authorityToRoleMap[$groupAuthorityId],
		];

		return $this->__defaultRoleKeyListFromNc2[$nc2SpaceType];
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

}
