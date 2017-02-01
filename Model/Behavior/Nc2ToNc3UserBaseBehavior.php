<?php
/**
 * Nc2ToNc3UserBaseBehavior
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
class Nc2ToNc3UserBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * IdMap of nc2 and nc3.
 *
 * @var array
 */
	private $__idMap = null;

/**
 * Put id map.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2UserId Nc2User user_id.
 * @param string $nc3User Nc3User data.
 * @return void
 */
	public function putIdMap(Model $model, $nc2UserId, $nc3User) {
		$this->_putIdMap($nc2UserId, $nc3User);
	}

/**
 * Get id map.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2UserId Nc2User user_id.
 * @return array|string Id map.
 */
	public function getIdMap(Model $model, $nc2UserId = null) {
		return $this->_getIdMap($nc2UserId);
	}

/**
 * Get Nc3 created_uer.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Data Nc2 data having insert_user_id and insert_user_name
 * @return string Nc3 created_uer.
 */
	public function getCreatedUser(Model $model, $nc2Data) {
		return $this->_getCreatedUser($nc2Data);
	}

/**
 * Put id map.
 *
 * @param string $nc2UserId Nc2User user_id.
 * @param string $nc3User Nc3User data.
 * @return void
 */
	protected function _putIdMap($nc2UserId, $nc3User) {
		$this->__idMap[$nc2UserId] = [
			'User' => [
				'id' => $nc3User['User']['id'],
				'handlename' => $nc3User['User']['handlename']
			]
		];
	}

/**
 * Get id map
 *
 * @param string $nc2UserId Nc2User user_id.
 * @return array|string Id map.
 */
	protected function _getIdMap($nc2UserId = null) {
		if (isset($nc2UserId)) {
			return Hash::get($this->__idMap, [$nc2UserId]);
		}

		return $this->__idMap;
	}

/**
 * Get Nc3 created_uer.
 *
 * @param array $nc2Data Nc2 data having insert_user_id and insert_user_name
 * @return string Nc3 created_uer.
 */
	protected function _getCreatedUser($nc2Data) {
		$nc2UserId = $nc2Data['insert_user_id'];
		if (!$nc2UserId) {
			return null;
		}

		$map = $this->_getIdMap($nc2UserId);
		if ($map) {
			return $map['User']['id'];
		}

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');

		// ハンドルが同一のNc3User.idをcreated_uerとする？
		// 同一人物の保証ができない。
		// 移行時にmapデータを読み込むようにして、mapデータになければ削除ユーザーとして登録するのが良い気がする
		// 削除ユーザーとしてmapされているユーザーが移行対象になった場合要注意
		//   →updaetしても削除扱いなので、新規ユーザーとして登録しないといけない
		$user = $User->findByHandlenameAndIsDeleted(
			$nc2Data['insert_user_name'],
			'1',
			null,
			null,
			-1
		);
		if ($user) {
			$this->_putIdMap($nc2UserId, $user);
			return $user['User']['id'];
		}

		$saveOptions = [
			'validate' => false,
			'fieldList' => [
				'handlename',
				'is_deleted',
				'created_user',
				'created',
				'modified_user',
				'modified',
			],
			'callbacks' => false,
		];
		$data = [
			'User' => [
				'handlename' => $nc2Data['insert_user_name'],
				'is_deleted' => '1',
			]
		];
		$User->create($data);
		$data = $User->save($data, $saveOptions);

		$this->_putIdMap($nc2UserId, $data);

		return $User->id;
	}

}
