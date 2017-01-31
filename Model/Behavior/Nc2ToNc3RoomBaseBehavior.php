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

/**
 * Nc2ToNc3UserBaseBehavior
 *
 */
class Nc2ToNc3RoomBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * IdMap of nc2 and nc3.
 *
 * @var array
 */
	private $__idMap = null;

/**
 * Nc3Room default_role_key list from Nc2Config default_entry_role_auth_group.
 *
 * @var array
 */
	private $__defaultRoleKeyListFromNc2 = null;

/**
 * Put id map.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2RoomId Nc2Page room_id.
 * @param string $nc3Room Nc3Room data.
 * @return void
 */
	public function putIdMap(Model $model, $nc2RoomId, $nc3Room) {
		$this->_putIdMap($nc2RoomId, $nc3Room);
	}

/**
 * Get id map.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2UserId Nc2User id.
 * @return array|string Id map.
 */
	public function getIdMap(Model $model, $nc2UserId = null) {
		return $this->_getIdMap($nc2UserId);
	}

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
 * Put id map.
 *
 * @param string $nc2RoomId Nc2Page room_id.
 * @param string $nc3Room Nc3Room data.
 * @return void
 */
	protected function _putIdMap($nc2RoomId, $nc3Room) {
		$this->__idMap[$nc2RoomId] = [
			'Room' => [
				'id' => $nc3Room['Room']['id']
			]
		];
	}

/**
 * Get id map
 *
 * @param string $nc2RoomId Nc2Page room_id.
 * @return array|string Id map.
 */
	protected function _getIdMap($nc2RoomId = null) {
		if (isset($nc2RoomId)) {
			return Hash::get($this->__idMap, [$nc2RoomId]);
		}

		return $this->__idMap;
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

}
