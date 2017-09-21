<?php
/**
 * Nc2ToNc3Reservation
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Reservation
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
 * @method void changeNc3CurrentLanguage($langDirName = null)
 * @method void restoreNc3CurrentLanguage()
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)]
 *
 */
class Nc2ToNc3Reservation extends Nc2ToNc3AppModel {

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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Reservation',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Migration start.'));

		if (!$this->_migrateReservation()) {
			return false;
		}

		if (!$this->_migrateCategory()) {
			return false;
		}

		if (!$this->_migrateLocation()) {
			return false;
		}

		//if (!$this->_migrateLocationsRoom()) {
		//	return false;
		//}

		//if (!$this->_migrateLocationReservable()) {
		//	return false;
		//}

		if (!$this->_migrateRrule()) {
			return false;
		}

		if (!$this->_migrateEvent()) {
			return false;
		}

		$Nc2Timeframe = $this->getNc2Model('reservation_timeframe');
		$nc2Timeframes = $Nc2Timeframe->find('all');
		if (!$this->_saveNc3ReservationTimeframeFromNc2($nc2Timeframes)) {
			return false;
		}

		if (!$this->_migrateBlockToFrameSetting()) {
			return false;
		}

		return true;
	}

/**
 * NC3のreservationsテーブルの移行
 *
 * @return bool
 */
	protected function _migrateReservation() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Reservation start.'));

		// すでにreservationsテーブルにレコードあれば何もしない
		$Reservation = ClassRegistry::init('Reservations.Reservation');
		$count = $Reservation->find('count', []);
		if ($count) {
			$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Reservation is exist.'));
			return true;
		}

		// NC3に移行されたblockテーブルのレコードからreservationsテーブルにインサートする
		$Block = ClassRegistry::init('Blocks.Block');
		$data = $Block->findByPluginKey('reservations');
		if ($data) {
			// あれば取得したデータをつかう
		} else {
			// なかったら1つブロックをつくる
			$data = [
				'Block' => [
					'room_id' => 1,
					'plugin_key' => 'reservations',
					'public_type' => 1
				]
			];
			$Block->create();
			$Block->begin();
			if (!$data = $Block->save($data)) {
				$Block->rollback();
				return false;
			}
			$Block->commit();
		}
		$this->_blockId = $data['Block']['id'];

		$reservation = [
			'Reservation' => [
				'block_key' => $data['Block']['key']
			]
		];
		$Reservation->create();
		$Reservation->begin();
		if (!$Reservation->save($reservation)) {
			$Reservation->rollback();
			return false;
		}
		$Reservation->commit();

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Reservation end.'));
		return true;
	}

/**
 * 施設カテゴリの移行
 *
 * @return bool
 */
	protected function _migrateCategory() {
		$Reservation = ClassRegistry::init('Reservations.Reservation');
		$Block = ClassRegistry::init('Blocks.Block');
		$block = $Block->findByPluginKey('reservations');

		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		// カテゴリID1は「カテゴリなし」として使われてるので移行しない
		$query['conditions'] = [
			'category_id > ' => 1
		];

		$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('reservation_category', $query);

		$data = $Reservation->find('first', []);
		$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList, $block['Block']['id']);
		$data['Block'] = $block['Block']; //これがないと2回目の移行でカテゴリ削除がされない
		$Reservation->save($data); // CategoryBehaviorを使ってカテゴリデータを保存する

		if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $block['Block']['id'])) {
			return false;
		}

		return true;
	}

/**
 * 施設の移行
 *
 * @return bool
 * @throws Exception
 */
	protected function _migrateLocation() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Location start.'));

		$Nc2Model = $this->getNc2Model('reservation_location');
		$nc2Records = $Nc2Model->find('all');

		$User = ClassRegistry::init('Users.User');
		$approvalUsers = $User->find('all', [
			'conditions' => [
				'User.role_key' => [
					'system_administrator',
					'administrator'
				]
			],
			'fields' => ['User.id'],
			'recursive' => -1
		]);

		$ApplovalUser = ClassRegistry::init('Reservations.ReservationLocationsApprovalUser');

		$Nc3Model = ClassRegistry::init('Reservations.ReservationLocation');

		$LocationsReservable = ClassRegistry::init('Reservations.ReservationLocationReservable');

		foreach ($nc2Records as $nc2Record) {
			$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationLocation($nc2Record);
				if (!$data) {
					$Nc3Model->rollback();
					continue;
				}
				$Nc3Model->create();
				if (!$savedData = $Nc3Model->save($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Nc3Model->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Record) . "\n" .
						var_export($Nc3Model->validationErrors, true);
					$this->writeMigrationLog($message);
					$Nc3Model->rollback();
					continue;
				}

				// 承認者を登録しておく
				$locationKey = $savedData['ReservationLocation']['key'];
				foreach ($approvalUsers as $user) {
					$approvalUser = [
						'ReservationLocationsApprovalUser' => [
							'location_key' => $locationKey,
							'user_id' => $user['User']['id'],
						]
					];
					$ApplovalUser->create();
					if (!$ApplovalUser->save($approvalUser)) {
						$Nc3Model->rollback();

						// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
						// @see https://phpmd.org/rules/design.html
						$message = $this->getLogArgument($approvalUser) . "\n" .
							var_export($Nc3Model->validationErrors, true);
						$this->writeMigrationLog($message);
						continue;
					}
				}

				// Locationroomを移行する
				$nc2LocationId = $nc2Record['Nc2ReservationLocation']['location_id'];
				if (!$this->_migrateLocationsRoom($nc2LocationId, $savedData)) {
					$Nc3Model->rollback();
					continue;
				}

				// LocationReservableを登録する
				if (!$this->_migrateLocationReservable($nc2LocationId, $savedData)) {
					$Nc3Model->rollback();
					continue;
				}

				//$data = $this->_generateNc3LocationReservable($nc2Record, $savedData);
				////$locationKey = $data['ReservationLocation']['key'];
				//if (!$LocationsReservable->saveReservable($locationKey, $data)) {
				//	$Nc3Model->rollback();
				//	continue;
				//}

				$nc2Id = $nc2Record['Nc2ReservationLocation']['location_id'];
				$idMap = [
					$nc2Id => $Nc3Model->id
				];
				$this->saveMap('ReservationLocation', $idMap);

				$Nc3Model->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Location end.'));
		return true;
	}

/**
 * NC2 location dataからNC3 Location dataを返す
 *
 * @param array $nc2Record NC2 location data
 * @return array
 */
	protected function _generateNc3ReservationLocation($nc2Record) {
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationLocation', $nc2Record['Nc2ReservationLocation']['location_id']);
		if ($mapIdList) {
			// 移行済みなのでコンバートしない
			return [];
		}

		$Nc2LocationDetail = $this->getNc2Model('reservation_location_details');
		$detail = $Nc2LocationDetail->findByLocationId($nc2Record['Nc2ReservationLocation']['location_id']);

		$Block = ClassRegistry::init('Blocks.Block');
		$block = $Block->findByPluginKey('reservations');

		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		$categoryId = $Nc2ToNc3Category->getNc3CategoryId($block['Block']['id'], $nc2Record['Nc2ReservationLocation']['category_id']);
		if ($categoryId == 0) {
			$categoryId = null;
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		$data = [
			'ReservationLocation' => [
				'language_id' => $this->getLanguageIdFromNc2(),
				'category_id' => $categoryId,
				'location_name' => $nc2Record['Nc2ReservationLocation']['location_name'],
				'detail' => $detail['Nc2ReservationLocationDetail']['description'],
				'add_authority' => 0, // NC3では未使用
				'time_table' => $this->_convertTimeTable($nc2Record['Nc2ReservationLocation']['time_table']),
				'start_time' => $this->_convertLocationTime($nc2Record['Nc2ReservationLocation']['start_time']),
				'end_time' => $this->_convertLocationTime($nc2Record['Nc2ReservationLocation']['end_time']),
				'timezone' => $this->convertTimezone($nc2Record['Nc2ReservationLocation']['timezone_offset']),
				'use_private' => $nc2Record['Nc2ReservationLocation']['use_private_flag'],
				'use_auth_flag' => $nc2Record['Nc2ReservationLocation']['use_auth_flag'],
				'use_all_rooms' => $nc2Record['Nc2ReservationLocation']['allroom_flag'],
				'use_workflow' => 0, //使わない
				'weight' => $nc2Record['Nc2ReservationLocation']['display_sequence'],
				'contact' => $detail['Nc2ReservationLocationDetail']['contact'],

				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Record['Nc2ReservationLocation']),
				'created' => $this->convertDate($nc2Record['Nc2ReservationLocation']['insert_time']),
			],
		];
		return $data;
	}

/**
 * Migrate LocationsRoom
 *
 * @return bool
 * @throws Exception
 */
	protected function _migrateLocationsRoom($locationId, $savedData) {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation LocationsRoom start.'));

		$Nc2Model = $this->getNc2Model('reservation_location_rooms');
		// NC2 location_idを元にそのlocation_roomsデータだけを移行する
		$conditions = [
			'location_id' => $locationId
		];
		$nc2Records = $Nc2Model->find('all', ['conditions' => $conditions]);

		$Nc3Model = ClassRegistry::init('Reservations.ReservationLocationsRoom');
		foreach ($nc2Records as $nc2Record) {
			//$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationLocationsRoom($nc2Record, $savedData);
				if (!$data) {
					$Nc3Model->rollback();
					continue;
				}
				$Nc3Model->create();
				if (!$Nc3Model->save($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Nc3Model->rollback();

					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Record) . "\n" .
						var_export($Nc3Model->validationErrors, true);
					$this->writeMigrationLog($message);
					$Nc3Model->rollback();
					continue;
				}

				// 関連テーブルなのでマッピングレコード不要

				//$Nc3Model->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation LocationsRoom end.'));
		return true;
	}

/**
 * NC2 location_rooms dataからNC3 LocationsRoom dataを返す
 *
 * @param array $nc2Record NC2 location_room data
 * @return array
 */
	protected function _generateNc3ReservationLocationsRoom($nc2Record, $nc3Location) {
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		//$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		//$nc2LocationId = $nc2Record['Nc2ReservationLocationRoom']['location_id'];
		// nc3location.id取得
		//$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationLocation', $nc2LocationId);
		// ロケーションキー取得
		//$ReservationLocation = ClassRegistry::init('Reservations.ReservationLocation');
		//$location = $ReservationLocation->findById($mapIdList[$nc2LocationId]);
		$location = $nc3Location;

		$nc3LocationKey = $location['ReservationLocation']['key'];
		// nc3roomId取得
		$nc2RoomId = $nc2Record['Nc2ReservationLocationRoom']['room_id'];
		/* @var Nc2ToNc3Room $Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$room = $Nc2ToNc3Room->getMap($nc2RoomId);
		$nc3RoomId = $room['Room']['id'];

		// データあるならINSERTしない
		// なければデータ返す
		$LocationsRoom = ClassRegistry::init('Reservations.ReservationLocationsRoom');
		$conditions = [
			'ReservationLocationsRoom.reservation_location_key' => $nc3LocationKey,
			'ReservationLocationsRoom.room_id' => $nc3RoomId
		];
		if ($LocationsRoom->find('count', ['conditions' => $conditions])) {
			return [];
		}
		$data = [
			'ReservationLocationsRoom' => [
				'reservation_location_key' => $nc3LocationKey,
				'room_id' => $nc3RoomId,
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Record['Nc2ReservationLocationRoom']),
				'created' => $this->convertDate($nc2Record['Nc2ReservationLocationRoom']['insert_time']),

			]
		];
		return $data;
	}

/**
 * Migrate LocationReservable
 *
 * @return bool
 * @throws Exception
 */
	protected function _migrateLocationReservable($nc2LocationId, $savedData) {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation LocationReservable start.'));

		$Nc2Model = $this->getNc2Model('reservation_location');
		$nc2Records = $Nc2Model->find('all', [
			'conditions' => [
				'location_id' => $nc2LocationId
			]
		]);

		$Nc3Model = ClassRegistry::init('Reservations.ReservationLocation');
		$LocationsReservable = ClassRegistry::init('Reservations.ReservationLocationReservable');

		foreach ($nc2Records as $nc2Record) {
			//$Nc3Model->begin();
			try {
				$data = $this->_generateNc3LocationReservable($nc2Record, $savedData);

				$locationKey = $data['ReservationLocation']['key'];

				if (!$LocationsReservable->saveReservable($locationKey, $data)) {
					$Nc3Model->rollback();
					continue;
				}

				$Nc3Model->commit();

			} catch (Exception $ex) {
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation LocationReservable end.'));
		return true;
	}

/**
 * Rruleの移行
 *
 * @return bool
 * @throws Exception
 */
	protected function _migrateRrule() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Rrule start.'));

		$Nc2Model = $this->getNc2Model('reservation_reserve_details');
		$nc2Records = $Nc2Model->find('all');

		$Nc3Model = ClassRegistry::init('Reservations.ReservationRrule');
		foreach ($nc2Records as $nc2Record) {
			$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationRrule($nc2Record);
				if (!$data) {
					$Nc3Model->rollback();
					continue;
				}
				$Nc3Model->create();
				if (!$Nc3Model->save($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Nc3Model->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Record) . "\n" .
						var_export($Nc3Model->validationErrors, true);
					$this->writeMigrationLog($message);
					$Nc3Model->rollback();
					continue;
				}

				$nc2Id = $nc2Record['Nc2ReservationReserveDetail']['reserve_details_id'];
				$idMap = [
					$nc2Id => $Nc3Model->id
				];
				$this->saveMap('ReservationRrule', $idMap);

				$Nc3Model->commit();

			} catch (Exception $ex) {
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Rrule end.'));
		return true;
	}

/**
 * NC2 reserve_details dataからNC3 rruelをかえす
 *
 * @param array $nc2Record NC2 reserve_details data
 * @return array
 */
	protected function _generateNc3ReservationRrule($nc2Record) {
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationRrule', $nc2Record['Nc2ReservationReserveDetail']['reserve_details_id']);
		if ($mapIdList) {
			// 移行済みなのでコンバートしない
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		// reservationId
		$Reservation = ClassRegistry::init('Reservations.Reservation');
		$reservation = $Reservation->find('first');
		$reservationId = $reservation['Reservation']['id'];

		// ireservation_uid
		$Nc2Reserve = $this->getNc2Model('reservation_reserve');
		$reserve = $Nc2Reserve->find('first', [
			'conditions' => [
				'reserve_details_id' => $nc2Record['Nc2ReservationReserveDetail']['reserve_details_id'],
			],
			'order' => 'reserve_id ASC'
		]);

		$ireservationUid = ReservationRruleUtil::generateIcalUid(
			$reserve['Nc2ReservationReserve']['start_date'],
			$reserve['Nc2ReservationReserve']['start_time']);

		// roomId
		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		if ($nc2Record['Nc2ReservationReserveDetail']['room_id'] > 0) {
			$room = $Nc2ToNc3Room->getMap($nc2Record['Nc2ReservationReserveDetail']['room_id']);
			$roomId = $room['Room']['id'];
		} else {
			//$roomId = 1; // 無指定はパブリック扱いにしておく
			$roomId = 0; // 無指定
		}

		$rrule = ($nc2Record['Nc2ReservationReserveDetail']['rrule'] === null) ? '' :
			$nc2Record['Nc2ReservationReserveDetail']['rrule'];

		$data = [
			'ReservationRrule' => [
				'reservation_id' => $reservationId,
				'name' => '',
				'rrule' => $rrule,
				'ireservation_uid' => $ireservationUid,
				'ireservation_comp_name' => 'reservations',
				'room_id' => $roomId,
				'created_user' => $Nc2ToNc3User->getCreatedUser($reserve['Nc2ReservationReserve']),
				'created' => $this->convertDate($reserve['Nc2ReservationReserve']['insert_time']),
			]
		];
		return $data;
	}

/**
 * 予約の移行
 *
 * @return bool
 * @throws Exception
 */
	protected function _migrateEvent() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Event start.'));

		$Nc2Model = $this->getNc2Model('reservation_reserve');
		$nc2Records = $Nc2Model->find('all');

		$Nc3Model = ClassRegistry::init('Reservations.ReservationEvent');
		foreach ($nc2Records as $nc2Record) {
			$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationEvent($nc2Record);
				if (!$data) {
					$Nc3Model->rollback();
					continue;
				}
				$Nc3Model->create();
				if (!$Nc3Model->save($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Nc3Model->rollback();

					$message = $this->getLogArgument($nc2Record) . "\n" .
						var_export($Nc3Model->validationErrors, true);
					$this->writeMigrationLog($message);
					$Nc3Model->rollback();
					continue;
				}

				$nc2Id = $nc2Record['Nc2ReservationReserve']['reserve_id'];
				$idMap = [
					$nc2Id => $Nc3Model->id
				];
				$this->saveMap('ReservationEvent', $idMap);

				$Nc3Model->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Event end.'));
		return true;
	}

/**
 * NC2 reserve dataからNC3 eventを返す
 *
 * @param array $nc2Record NC2 reserve data
 * @return array
 */
	protected function _generateNc3ReservationEvent($nc2Record) {
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationEvent', $nc2Record['Nc2ReservationReserve']['reserve_id']);
		if ($mapIdList) {
			// 移行済みなのでコンバートしない
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');

		$Nc3Model = ClassRegistry::init('Reservations.ReservationLocation');

		// location_key
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationLocation');
		$location = $Nc3Model->findById(
			$mapIdList[$nc2Record['Nc2ReservationReserve']['location_id']]
		);
		$locationKey = $location['ReservationLocation']['key'];

		// room_id
		if ($nc2Record['Nc2ReservationReserve']['room_id'] > 0) {
			$room = $Nc2ToNc3Room->getMap($nc2Record['Nc2ReservationReserve']['room_id']);
			$roomId = $room['Room']['id'];
		} else {
			//$roomId = 1; // ルーム無指定ならパブリックルームにする
			$roomId = 0; // ルーム無指定
		}

		// target_user
		$targetUser = $Nc2ToNc3User->getMap($nc2Record['Nc2ReservationReserve']['user_id']);

		// contact, description
		$Nc2ReserveDetail = $this->getNc2Model('reservation_reserve_details');
		$nc2Detail = $Nc2ReserveDetail->find('first', [
			'conditions' => [
				'reserve_details_id' => $nc2Record['Nc2ReservationReserve']['reserve_details_id']
			]
		]);
		$contact = $nc2Detail['Nc2ReservationReserveDetail']['contact'];
		$description = $nc2Detail['Nc2ReservationReserveDetail']['description'];

		//$rrule = $nc2Detail['Nc2ReservationReserveDetail']['rrule'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationRrule', $nc2Detail['Nc2ReservationReserveDetail']['reserve_details_id']);
		$rruleId = $mapIdList[$nc2Detail['Nc2ReservationReserveDetail']['reserve_details_id']];

		$titleiCon = $this->convertTitleIcon($nc2Record['Nc2ReservationReserve']['title_icon']);
		$titleiCon = ($titleiCon === null) ? '' : $titleiCon;
		$data = [
			'ReservationEvent' => [
				'reservation_rrule_id' => $rruleId,
				'room_id' => $roomId,
				'language_id' => $this->getLanguageIdFromNc2(),
				'target_user' => $targetUser['User']['id'],
				'title' => $nc2Record['Nc2ReservationReserve']['title'],
				'title_icon' => $titleiCon,
				'location' => '',
				'contact' => $contact,
				'description' => $description,
				'is_allday' => $nc2Record['Nc2ReservationReserve']['allday_flag'],
				'start_date' => $nc2Record['Nc2ReservationReserve']['start_date'],
				'start_time' => $nc2Record['Nc2ReservationReserve']['start_time'],
				'dtstart' => $nc2Record['Nc2ReservationReserve']['start_time_full'],
				'end_date' => $nc2Record['Nc2ReservationReserve']['end_date'],
				'end_time' => $nc2Record['Nc2ReservationReserve']['end_time'],
				'dtend' => $nc2Record['Nc2ReservationReserve']['end_time_full'],
				'timezone' => $this->convertTimezone($nc2Record['Nc2ReservationReserve']['timezone_offset']),
				'location_key' => $locationKey,
				'status' => WorkflowComponent::STATUS_PUBLISHED,
				'is_active' => 1,
				'is_latest' => 1,
				'recurrence_event_id' => 0,
				'exception_event_id' => 0,
				'is_enable_mail' => 0,
				'email_send_timing' => 5,
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Record['Nc2ReservationReserve']),
				'created' => $this->convertDate($nc2Record['Nc2ReservationReserve']['insert_time']),
			]
		];
		return $data;
	}

/**
 * Migrate FrameSetting
 *
 * @return bool
 */
	protected function _migrateBlockToFrameSetting() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation FrameSetting start.'));

		$Nc2Model = $this->getNc2Model('reservation_block');
		$nc2Records = $Nc2Model->find('all');
		if (!$this->_saveNc3ReservationFrameSettingFromNc2($nc2Records)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation FrameSetting end.'));
		return true;
	}

/**
 * save FrameSetting
 *
 * @param array $nc2Records NC2 block data
 * @return bool
 * @throws Exception
 */
	protected function _saveNc3ReservationFrameSettingFromNc2($nc2Records) {
		/* @var $Nc3Model ReservationFrameSetting */
		$Nc3Model = ClassRegistry::init('Reservations.ReservationFrameSetting');

		Current::write('Plugin.key', 'reservations');

		foreach ($nc2Records as $nc2Record) {
			$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationFrameSetting($nc2Record);
				if (!$data) {
					$Nc3Model->rollback();
					continue;
				}
				$Nc3Model->create();

				if (!$Nc3Model->save($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Nc3Model->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Record) . "\n" .
						var_export($Nc3Model->validationErrors, true);
					$this->writeMigrationLog($message);
					$Nc3Model->rollback();
					continue;
				}
				// 　権限セットしてたらここで解除
				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2Id = $nc2Record['Nc2ReservationBlock']['block_id'];
				$idMap = [
					$nc2Id => $Nc3Model->id
				];
				$this->saveMap('ReservationFrameSetting', $idMap);

				$Nc3Model->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		return true;
	}

/**
 * generate NC3 FrameSetting
 *
 * @param array $nc2Record NC2 block data
 * @return array
 */
	protected function _generateNc3ReservationFrameSetting($nc2Record) {
		$nc2Id = $nc2Record['Nc2ReservationBlock']['block_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationFrameSetting', $nc2Id);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		// NC2 display_start_time = defalt なら閲覧時刻により変動　固定なら0800 形式で時刻
		if ($nc2Record['Nc2ReservationBlock']['display_start_time'] == 'default') {
			// 開始時刻変動
			$displayStartTimeType = 0; // 変動
			$timelineBaseTime = 8; // 初期値は8時
		} else {
			// 開始時刻固定
			$displayStartTimeType = 1; // 固定
			// NC2は0800 形式だがNC3は数値（8時なら 8)
			$timelineBaseTime = (int)substr($nc2Record['Nc2ReservationBlock']['display_start_time'], 0, 2);
		}

		// $roomId
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$room = $Nc2ToNc3Room->getMap($nc2Record['Nc2ReservationBlock']['room_id']);
		$roomId = $room['Room']['id'];

		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$frame = $Nc2ToNc3Frame->getMap($nc2Record['Nc2ReservationBlock']['block_id']);
		$frameKey = $frame['Frame']['key'];

		// $categoryId
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		$categoryId = $Nc2ToNc3Category->getNc3CategoryId(
			$frame['Frame']['block_id'],
			$nc2Record['Nc2ReservationBlock']['category_id']);
		if ($categoryId == 0) {
			$categoryId = null;
		}

		//   $locationKey
		$locationKey = ''; // 一時的
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationLocation', $nc2Record['Nc2ReservationBlock']['location_id']);
		if ($mapIdList) {
			//  location取得してkeyをセットする
			$ReservationLocation = ClassRegistry::init('Reservations.ReservationLocation');
			$locationId = $mapIdList[$nc2Record['Nc2ReservationBlock']['location_id']];
			$location = $ReservationLocation->findById($locationId);
			$locationKey = $location['ReservationLocation']['key'];
		}

		switch($nc2Record['Nc2ReservationBlock']['display_type']) {
			case 1:
				// 月　施設別
				$displayType = 3;
				break;
			case 2:
				// 週　施設別
				$displayType = 4;
				break;
			case 3 :
				// 日　カテゴリ別
				$displayType = 2;
				break;
		}

		$data = [
			'ReservationFrameSetting' => [
				'frame_key' => $frameKey,
				'display_type' => $displayType,
				'location_key' => $locationKey,
				'category_id' => $categoryId,
				'display_timeframe' => $nc2Record['Nc2ReservationBlock']['display_timeframe'],
				'display_start_time_type' => $displayStartTimeType,
				'start_pos' => 0,
				'display_count' => 0,
				'is_myroom' => 0,
				'is_select_room' => 0,
				'room_id' => $roomId,
				'timeline_base_time' => $timelineBaseTime,
				'display_interval' => $nc2Record['Nc2ReservationBlock']['display_interval'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Record['Nc2ReservationBlock']),
				'created' => $this->convertDate($nc2Record['Nc2ReservationBlock']['insert_time']),
			],
		];
		return $data;
	}

/**
 * Save TimeFrame
 *
 * @param array $nc2Timeframes NC2 timeframe data
 * @return bool
 * @throws Exception
 */
	protected function _saveNc3ReservationTimeframeFromNc2($nc2Timeframes) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Reservation Timeframe Migration start.'));

		/* @var $Blog Blog */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Timeframe = ClassRegistry::init('Reservations.ReservationTimeframe');

		Current::write('Plugin.key', 'reservations');

		foreach ($nc2Timeframes as $nc2Timeframe) {
			$Timeframe->begin();
			try {
				$data = $this->_generateNc3ReservationTimeframe($nc2Timeframe);
				if (!$data) {
					$Timeframe->rollback();
					continue;
				}

				$Timeframe->create();

				if (!$Timeframe->saveTimeframe($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Timeframe->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Timeframe) . "\n" .
						var_export($Timeframe->validationErrors, true);
					$this->writeMigrationLog($message);
					$Timeframe->rollback();
					continue;
				}
				// 　権限セットしてたらここで解除
				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2TimeframeId = $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_id'];
				$idMap = [
					$nc2TimeframeId => $Timeframe->id
				];
				$this->saveMap('ReservationTimeframe', $idMap);

				$Timeframe->commit();

			} catch (Exception $ex) {
				$Timeframe->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  ReservationTimeframe data Migration end.'));
		return true;
	}

/**
 * generate NC3 Timeframe
 *
 * @param array $nc2Timeframe NC2 timeframe
 * @return array
 */
	protected function _generateNc3ReservationTimeframe($nc2Timeframe) {
		$nc2TimeframeId = $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationTimeframe', $nc2TimeframeId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		$data = [
			'ReservationTimeframe' => [
				'language_id' => $this->getLanguageIdFromNc2(),
				'title' => $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_name'],
				'start_time' => $this->_convertTimeframeTime( $nc2Timeframe['Nc2ReservationTimeframe']['start_time'], $nc2Timeframe['Nc2ReservationTimeframe']['timezone_offset']),
				// 150000形式からTIME形式→そのままでも入るので変換不要
				'end_time' => $this->_convertTimeframeTime($nc2Timeframe['Nc2ReservationTimeframe']['end_time'], $nc2Timeframe['Nc2ReservationTimeframe']['timezone_offset']),
				'timezone' => $this->convertTimezone($nc2Timeframe['Nc2ReservationTimeframe']['timezone_offset']),
				'color' => $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_color'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Timeframe['Nc2ReservationTimeframe']),
				'created' => $this->convertDate($nc2Timeframe['Nc2ReservationTimeframe']['insert_time']),

			],
		];
		return $data;
	}

/**
 * convert TimeFrame time
 *
 * @param string $time 150000形式
 * @param float $timezoneOffset +12.0〜-12.0
 * @return string
 */
	protected function _convertTimeframeTime($time, $timezoneOffset) {
		// DBにはUTC時間で保存されてる
		// saveTimeframe()にはtimezone で指定したタイムゾーンの時間で渡す
		$hour = substr($time, 0, 2);
		$min = substr($time, 2, 2);
		$time = $hour . ':' . $min;

		$userTimezone = $this->convertTimezone($timezoneOffset);

		$time = new DateTime($time, new DateTimeZone('UTC'));
		$time->setTimezone(new DateTimeZone($userTimezone));
		$ret = $time->format('H:i');
		return $ret;
	}

/**
 * convert Location time
 *
 * @param string $time 20170101000000 形式
 * @return false|string
 */
	protected function _convertLocationTime($time) {
		return date('Y-m-d H:i:s', strtotime($time));
	}

/**
 * convert TimeTable
 *
 * @param string $timeTable NC2タイムテーブル
 * @return string
 */
	protected function _convertTimeTable($timeTable) {
		$nc2Table = [
			'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', ','
		];
		$nc3Table = [
			'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', '|'
		];
		return str_replace($nc2Table, $nc3Table, $timeTable);
	}

/**
 * generate LocationReservable data
 *
 * @param array $nc2Record nc2location data
 * @return array
 */
	protected function _generateNc3LocationReservable($nc2Record, $nc3Location) {
		$LocationsRoom = ClassRegistry::init('Reservations.ReservationLocationsRoom');
		$RoomRole = ClassRegistry::init('Rooms.RoomRole');

		//$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		//$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationLocation');

		//$Nc3Model = ClassRegistry::init('Reservations.ReservationLocation');

		$data = [];
		//$location = $Nc3Model->findById(
		//	$mapIdList[$nc2Record['Nc2ReservationLocation']['location_id']]
		//);
		$location = $nc3Location;

		$data['ReservationLocation'] = $location['ReservationLocation'];
		$locationKey = $location['ReservationLocation']['key'];

		$locationRooms = $LocationsRoom->find(
			'all',
			[
				'conditions' => [
					'ReservationLocationsRoom.reservation_location_key' => $locationKey
				]
			]
		);
		if ($locationRooms) {
			$roomIdList = Hash::combine(
				$locationRooms,
				'{n}.ReservationLocationsRoom.id',
				'{n}.ReservationLocationsRoom.id'
			);
			$data['ReservationLocationsRoom']['room_id'] = $roomIdList;
		}

		switch ($nc2Record['Nc2ReservationLocation']['add_authority']) {
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
		}

		// Roleをレベル低い順に取得
		$roomRoles = $RoomRole->find('all', ['order' => 'level ASC']);
		$value = 0;
		foreach ($roomRoles as $roomRole) {
			$roleKey = $roomRole['RoomRole']['role_key'];
			if ($roleKey == $borderLine) {
				$value = 1;
			}
			$data['ReservationLocationReservable'][$roleKey]['value'] =
				$value;
		}
		return $data;
	}
}