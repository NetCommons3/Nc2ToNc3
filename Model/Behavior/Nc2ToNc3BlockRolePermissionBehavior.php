<?php
/** @noinspection PhpUnusedParameterInspection */
/**
 * Nc2ToNc3BlockSettingBehavior
 *
 * @author Japan Science and Technology Agency
 * @author National Institute of Informatics
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3BlogBehavior
 *
 * @author WithOne Company Limited. <nc@withone.co.jp>
 * @package Researchmap\Nc2ToNc3
 */
class Nc2ToNc3BlockRolePermissionBehavior extends Nc2ToNc3BaseBehavior {

/**
 * __getRolesRoomIdList
 *
 * @param string $nc3RoomId ルームID
 * @return array
 * @throws CakeException
 */
	private function __getRolesRoomIdList($nc3RoomId) {
		$RolesRoom = ClassRegistry::init('Rooms.RolesRoom');
		$rolesRooms = $RolesRoom->find('all', [
			'conditions' => [
				'RolesRoom.room_id' => $nc3RoomId
			],
			'fields' => ['RolesRoom.id', 'RolesRoom.role_key'],
			'recursive' => -1
		]);
		$rolesRoomIdByRoleKey = Hash::combine($rolesRooms, '{n}.RolesRoom.role_key', '{n}.RolesRoom.id');

		return $rolesRoomIdByRoleKey;
	}

/**
 * __getBorderRole
 *
 * @param int $nc2AuthorityCode NC2での権限
 * @return string
 */
	private function __getBorderRole($nc2AuthorityCode) {
		$borderLine = null;
		switch ($nc2AuthorityCode) {
			case 4:
				// NC2主担以上→Nc3ルーム管理者以上
				$borderLine = 'room_administrator';
				break;
			case 3:
				//NC2モデレータ以上→NC3編集者以上
				$borderLine = 'editor';
				break;
			case 2:
				// NC2一般以上→NC3一般以上
				$borderLine = 'general_user';
				break;
			case 1:
				// NC2ゲスト→NC3ゲスト
				$borderLine = 'visitor';
				break;
		}

		return $borderLine;
	}

/**
 * メール権限配列の作成
 *
 * @param Model $model Model
 * @param int $nc2AuthorityCode NC2でのメール権限
 * @param string $nc3RoomId ルームID
 * @return array
 * @throws CakeException
 */
	public function makeMailPermissionData(Model $model, $nc2AuthorityCode, $nc3RoomId) {
		$rolesRoomIdByRoleKey = $this->__getRolesRoomIdList($nc3RoomId);
		$borderLine = $this->__getBorderRole($nc2AuthorityCode);

		// Roleをレベル低い順に取得
		$RoomRole = ClassRegistry::init('Rooms.RoomRole');
		$roomRoles = $RoomRole->find('all', ['order' => 'level ASC']);
		$receivable = 0;
		$data = [];
		foreach ($roomRoles as $roomRole) {
			$roleKey = $roomRole['RoomRole']['role_key'];
			if ($roleKey == $borderLine) {
				$receivable = 1;
			}

			if (!in_array($roleKey, ['room_administrator', 'chief_editor'])) {
				$data['BlockRolePermission']['mail_content_receivable'][$roleKey] = [
					'id' => null,
					'block_key' => null,
					'roles_room_id' => $rolesRoomIdByRoleKey[$roleKey],
					'value' => $receivable,
					'permission' => 'mail_content_receivable'
				];
			}

		}
		return $data;
	}

/**
 * コンテンツ権限配列の作成
 *
 * @param Model $model Model
 * @param int $nc2AuthorityCode NC2での投稿権限
 * @param int $nc3RoomId ルームID
 * @return mixed
 * @throws CakeException
 */
	public function makeContentPermissionData(Model $model, $nc2AuthorityCode, $nc3RoomId) {
		$rolesRoomIdByRoleKey = $this->__getRolesRoomIdList($nc3RoomId);
		$borderLine = $this->__getBorderRole($nc2AuthorityCode);
		if ($borderLine === 'visitor') {
			$borderLine = null;
		}

		// Roleをレベル低い順に取得
		$RoomRole = ClassRegistry::init('Rooms.RoomRole');
		$roomRoles = $RoomRole->find('all', ['order' => 'level ASC']);
		$creatable = 0;
		$commentPublishable = 0;
		$commentPublishableBorderLine = 'chief_editor'; // NC3のデフォルトにしとく
		$data = [];
		foreach ($roomRoles as $roomRole) {
			$roleKey = $roomRole['RoomRole']['role_key'];
			if ($roleKey == $borderLine) {
				$creatable = 1;
			}
			if ($roleKey == $commentPublishableBorderLine) {
				$commentPublishable = 1;
			}

			// content_publishable はデータとして挿入しなくなったのでコメントアウト
			//// content_publishableはルーム管理, visitor のレコードはつくらない
			//if (!in_array($roleKey, ['room_administrator', 'visitor'])){
			//	$data['BlockRolePermission']['content_publishable'][$roleKey] = [
			//		'id' => null,
			//		'roles_room_id' => $rolesRoomIdByRoleKey[$roleKey],
			//		'value' => 0,
			//		'permission' => 'content_publishable'
			//	];
			//}

			// content_creatableはgeneral_userだけ
			if ($roleKey == 'general_user') {
				$data['BlockRolePermission']['content_creatable'][$roleKey] = [
					'id' => null,
					'roles_room_id' => $rolesRoomIdByRoleKey[$roleKey],
					'value' => $creatable,
					'permission' => 'content_creatable'
				];
			}

			// コメント投稿権限とコメント承認権限は
			// room_administrator, chief_editor, visitor以外を設定する
			if (!in_array($roleKey, ['room_administrator', 'chief_editor', 'visitor'])) {
				$data['BlockRolePermission']['content_comment_publishable'][$roleKey] = [
					'id' => null,
					'roles_room_id' => $rolesRoomIdByRoleKey[$roleKey],
					'value' => $commentPublishable,
					'permission' => 'content_comment_publishable'
				];

				$data['BlockRolePermission']['content_comment_creatable'][$roleKey] = [
					'id' => null,
					'roles_room_id' => $rolesRoomIdByRoleKey[$roleKey],
					'value' => 1,
					'permission' => 'content_comment_creatable'
				];
			}

		}
		/*
		 * roles_room_id, block_key, permission
		 */
		//$data['BlockRolePermission']['content_comment_creatable']['visitor'] = [
		//	'roles_room_id' => $rolesRoomIdByRoleKey[$roleKey],
		//	'value' => 0,
		//	'permission' => 'content_comment_creatable'
		//];
		return $data;
	}

}
