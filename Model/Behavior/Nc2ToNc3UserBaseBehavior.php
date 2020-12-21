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
 * Get Nc3 modified_uer.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Data Nc2 data having insert_user_id and insert_user_name
 * @return string Nc3 created_uer.
 */
	public function getModifiedUser(Model $model, $nc2Data) {
		return $this->_getModifiedUser($nc2Data);
	}

/**
 * Get map
 *
 * @param array|string $nc2UserIds Nc2User user_id.
 * @return array Map data with Nc2User user_id as key.
 */
	protected function _getMap($nc2UserIds = null) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $User User */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$User = ClassRegistry::init('Users.User');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('User', $nc2UserIds);
		$query = [
			'fields' => [
				'User.id',
				'User.handlename',
			],
			'conditions' => [
				'User.id' => $mapIdList
			],
			'recursive' => -1,
			'callbacks' => false
		];
		$users = $User->find('all', $query);
		if (!$users) {
			return $users;
		}

		$map = [];
		foreach ($users as $user) {
			$nc2Id = array_search($user['User']['id'], $mapIdList);
			$map[$nc2Id] = $user;
		}

		if (is_string($nc2UserIds)) {
			$map = $map[$nc2UserIds];
		}

		return $map;
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

		$map = $this->_getMap($nc2UserId);
		if ($map) {
			return $map['User']['id'];
		}

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');
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
		$User->save($data, $saveOptions);

		$idMap = [
			$nc2UserId => $User->id
		];
		$this->_saveMap('User', $idMap);

		return $User->id;
	}
/**
 * Get Nc3 modified_uer.
 *
 * @param array $nc2Data Nc2 data having update_user_id and update_user_name
 * @return string Nc3 updated_user.
 */
	protected function _getModifiedUser($nc2Data) {
		$nc2UserId = $nc2Data['update_user_id'];
		if (!$nc2UserId) {
			return null;
		}

		$map = $this->_getMap($nc2UserId);
		if ($map) {
			return $map['User']['id'];
		}

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');
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
				'handlename' => $nc2Data['update_user_name'],
				'is_deleted' => '1',
			]
		];
		$User->create($data);
		$User->save($data, $saveOptions);

		$idMap = [
			$nc2UserId => $User->id
		];
		$this->_saveMap('User', $idMap);

		return $User->id;
	}

}
