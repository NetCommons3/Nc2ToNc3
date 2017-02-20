<?php
/**
 * Nc2ToNc3RoomRole
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3RoomRole
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
 *
 */
class Nc2ToNc3RoomRole extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Base'];

/**
 * Map data.
 *
 * @var array
 */
	private $__map = null;

/**
 * Get map
 *
 * @param array|string $nc2RoleAuthorityIds Nc2Authority.id.
 * @return array Id map.
 */
	public function getMap($nc2RoleAuthorityIds = null) {
		// データの移行はしない(Nc3は画面からRoomRoleデータを追加できない。)
		// Nc2Authority.idとNc3RolesRoom.role_keyの対応付けのみ行う
		// Nc2Authority.idをkeyにNc3RolesRoom.role_keyと対応付ける

		if (!$this->__map) {
			$this->__setMap();
		}

		if (!isset($nc2RoleAuthorityIds)) {
			return $this->__map;
		}

		if (is_string($nc2RoleAuthorityIds)) {
			return Hash::get($this->__map, [$nc2RoleAuthorityIds]);
		}

		foreach ($nc2RoleAuthorityIds as $nc2RoleAuthorityId) {
			$map[$nc2RoleAuthorityId] = Hash::get($this->__map, [$nc2RoleAuthorityIds]);
		}

		return $map;
	}

/**
 * Set map
 *
 * @return void.
 */
	private function __setMap() {
		$this->__map = [
			'2' => [
				'RolesRoom' => [
					'role_key' => 'room_administrator'
				]
			],
			'4' => [
				'RolesRoom' => [
					'role_key' => 'general_user'
				]
			],
			'5' => [
				'RolesRoom' => [
					'role_key' => 'visitor'
				]
			],
		];

		/* @var $Nc2Authoritiy AppModel */
		$Nc2Authoritiy = $this->getNc2Model('authorities');
		$nc2Moderators = $Nc2Authoritiy->findAllByUserAuthorityId('3', 'role_authority_id', null, null, null, -1);
		foreach ($nc2Moderators as $nc2Moderator) {
			$nc2RoleAuthorityId = $nc2Moderator['Nc2Authority']['role_authority_id'];
			$this->__map[$nc2RoleAuthorityId] = [
				'RolesRoom' => [
					'role_key' => 'editor'
				]
			];
		}
	}

}
