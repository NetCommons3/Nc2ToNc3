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

		$Nc2Timeframe = $this->getNc2Model('reservation_timeframe');
		$nc2Timeframes = $Nc2Timeframe->find('all');
		if (!$this->_saveNc3ReservationTimeframeFromNc2($nc2Timeframes)) {
			return false;
		}

		if (!$this->_migrateBlockToFrameSetting()) {
			return false;
		}


		return true;

		/* @var $Nc2Blog AppModel */
		$Nc2Journal = $this->getNc2Model('journal');
		$nc2Journals = $Nc2Journal->find('all');
		if (!$this->__saveNc3BlogFromNc2($nc2Journals)) {
			return false;
		}

		/* @var $Nc2JournalBlock AppModel */
		$Nc2JournalBlock = $this->getNc2Model('journal_block');
		$nc2JournalBlocks = $Nc2JournalBlock->find('all');
		if (!$this->__saveNc3BlogFrameSettingFromNc2($nc2JournalBlocks)) {
			return false;
		}

		$Nc2JournalPost = $this->getNc2Model('journal_post');
		$nc2JournalPosts = $Nc2JournalPost->findAllByParentId('0', null, null, null, null, -1);

		if (!$this->__saveNc3BlogEntryFromNc2($nc2JournalPosts)) {
			return false;
		}

		$query = [
			'conditions' => [
				'NOT' => [
					'parent_id' => '0'
				]
			],
			'recursive' => -1
		];
		$nc2JournalPosts = $Nc2JournalPost->find('all', $query);
		if (!$this->__saveNc3ContentCommentFromNc2($nc2JournalPosts)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Blog Migration end.'));
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
		if (!$Reservation->save($reservation)){
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
 */
	protected function _migrateLocation() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Location start.'));


		$Nc2Model = $this->getNc2Model('reservation_location');
		$nc2Records = $Nc2Model->find('all');

		$Nc3Model = ClassRegistry::init('Reservations.ReservationLocation');
		foreach ($nc2Records as $nc2Record) {
			$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationLocation($nc2Record);
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
				// TODO　権限セットしてたらここで解除
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

		return true;
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation Location end.'));

	}

	protected function _generateNc3ReservationLocation($nc2Record) {

		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('ReservationLocation', $nc2Record['Nc2ReservationLocation']['location_id']);
		if ($mapIdList){
			// 移行済みなのでコンバートしない
			return [];
		}

		$Nc2LocationDetailModel = $this->getNc2Model('reservation_location_details');
		$detail = $Nc2LocationDetailModel->findByLocationId($nc2Record['Nc2ReservationLocation']['location_id']);

		$Block = ClassRegistry::init('Blocks.Block');
		$block = $Block->findByPluginKey('reservations');

		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		$categoryId = $Nc2ToNc3Category->getNc3CategoryId($block['Block']['id'], $nc2Record['Nc2ReservationLocation']['category_id']);

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

	protected function _migrateBlockToFrameSetting() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation FrameSetting start.'));


		$Nc2Model = $this->getNc2Model('reservation_block');
		$nc2Records = $Nc2Model->find('all');
		if (!$this->_saveNc3ReservationFrameSettingFromNc2($nc2Records)) {
			return false;
		}

		return true;
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Reservation FrameSetting end.'));
	}
	protected function _saveNc3ReservationFrameSettingFromNc2($nc2Records) {

		/* @var $Nc3Model ReservationFrameSetting */
		$Nc3Model = ClassRegistry::init('Reservations.ReservationFrameSetting');

		Current::write('Plugin.key', 'reservations');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$Blog->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$Blog->Behaviors->Block->settings = $Blog->actsAs['Blocks.Block'];

		//$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		//$Block = ClassRegistry::init('Blocks.Block');
		//$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Records as $nc2Record) {
			$Nc3Model->begin();
			try {
				$data = $this->_generateNc3ReservationFrameSetting($nc2Record);
				if (!$data) {
					$Nc3Model->rollback();
					continue;
				}
				//$query['conditions'] = [
				//	'journal_id' => $nc2Journal['Nc2Journal']['journal_id']
				//];
				//$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('journal_category', $query);
				//$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);
				//
				//// いる？
				//$nc3RoomId = $data['Block']['room_id'];
				//Current::write('Room.id', $nc3RoomId);
				// TODO 権限セット必要だったらやる
				//CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				//$BlocksLanguage->create();
				$Nc3Model->create();
				//$Block->create();
				//$Topic->create();

				//if (!$Timeframe->saveTimeframe($data)) {
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
				// TODO　権限セットしてたらここで解除
				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2Id = $nc2Record['Nc2ReservationBlock']['block_id'];
				$idMap = [
					$nc2Id => $Nc3Model->id
				];
				$this->saveMap('ReservationFrameSetting', $idMap);

				// これはブログのカテゴリ移行か
				//$nc3Blog = $Blog->findById($Blog->id, 'block_id', null, -1);
				//if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $nc3Blog['Blog']['block_id'])) {
				//	// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
				//	// @see https://phpmd.org/rules/design.html
				//	$message = $this->getLogArgument($nc2Journal);
				//	$this->writeMigrationLog($message);
				//	$Blog->rollback();
				//	continue;
				//}

				$Nc3Model->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Nc3Model->rollback($ex);
				throw $ex;
			}
		}

		//Current::remove('Room.id');
		//Current::remove('Plugin.key');

		return true;
	}

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
			$timelineBaseTime = (int) substr($nc2Record['Nc2ReservationBlock']['display_start_time'], 0, 2);
		}

		// $roomId
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$room = $Nc2ToNc3Room->getMap($nc2Record['Nc2ReservationBlock']['room_id']);
		$roomId = $room['Room']['id'];
		//$roomIdList = $Nc2ToNc3Map->getMapIdList('Room');
		//$roomId = $roomIdList[$nc2Record['Nc2ReservationBlock']['room_id']];
		//$roomId = Hash::get($roomIdList, $nc2Record['Nc2ReservationBlock']['room_id'], $nc2Record['Nc2ReservationBlock']['room_id']);
		// $frameKey
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$frame = $Nc2ToNc3Frame->getMap($nc2Record['Nc2ReservationBlock']['block_id']);
		$frameKey = $frame['Frame']['key'];

		// $categoryId
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
		$categoryId = $Nc2ToNc3Category->getNc3CategoryId(
			$frame['Frame']['block_id'],
			$nc2Record['Nc2ReservationBlock']['category_id']);

		//  TODO $locationKey
		$locationKey = ''; // 一時的

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

	protected function _saveNc3ReservationTimeframeFromNc2($nc2Timeframes) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Reservation Timeframe Migration start.'));

		/* @var $Blog Blog */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Timeframe = ClassRegistry::init('Reservations.ReservationTimeframe');

		Current::write('Plugin.key', 'reservations');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$Blog->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$Blog->Behaviors->Block->settings = $Blog->actsAs['Blocks.Block'];

		//$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		//$Block = ClassRegistry::init('Blocks.Block');
		//$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Timeframes as $nc2Timeframe) {
			$Timeframe->begin();
			try {
				$data = $this->_generateNc3ReservationTimeframe($nc2Timeframe);
				if (!$data) {
					$Timeframe->rollback();
					continue;
				}
				//$query['conditions'] = [
				//	'journal_id' => $nc2Journal['Nc2Journal']['journal_id']
				//];
				//$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('journal_category', $query);
				//$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);
				//
				//// いる？
				//$nc3RoomId = $data['Block']['room_id'];
				//Current::write('Room.id', $nc3RoomId);
				// TODO 権限セット必要だったらやる
				//CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				//$BlocksLanguage->create();
				$Timeframe->create();
				//$Block->create();
				//$Topic->create();

				//if (!$Timeframe->saveTimeframe($data)) {
				if (!$Timeframe->save($data)) {
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
				// TODO　権限セットしてたらここで解除
				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2TimeframeId = $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_id'];
				$idMap = [
					$nc2TimeframeId => $Timeframe->id
				];
				$this->saveMap('ReservationTimeframe', $idMap);

				// これはブログのカテゴリ移行か
				//$nc3Blog = $Blog->findById($Blog->id, 'block_id', null, -1);
				//if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $nc3Blog['Blog']['block_id'])) {
				//	// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
				//	// @see https://phpmd.org/rules/design.html
				//	$message = $this->getLogArgument($nc2Journal);
				//	$this->writeMigrationLog($message);
				//	$Blog->rollback();
				//	continue;
				//}

				$Timeframe->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Timeframe->rollback($ex);
				throw $ex;
			}
		}

		//Current::remove('Room.id');
		//Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  ReservationTimeframe data Migration end.'));
		return true;
	}

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
				'language_id' => $this->getLanguageIdFromNc2(), // TODO 言語はどこから取得するのが正しい?
				'title' => $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_name'],
				'start_time' => $this->_convertTimeframeTime( $nc2Timeframe['Nc2ReservationTimeframe']['start_time']),//
				// 150000形式からTIME形式→そのままでも入るので変換不要
				'end_time' => $this->_convertTimeframeTime($nc2Timeframe['Nc2ReservationTimeframe']['end_time']) ,//
				'timezone' => $this->convertTimezone($nc2Timeframe['Nc2ReservationTimeframe']['timezone_offset']),
				'color' => $nc2Timeframe['Nc2ReservationTimeframe']['timeframe_color'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Timeframe['Nc2ReservationTimeframe']),
				'created' => $this->convertDate($nc2Timeframe['Nc2ReservationTimeframe']['insert_time']),

			],
			//'BlogEntry' => [
			//	'blog_key' => $nc3Blog['Blog']['key'],
			//	'status' => $nc3Status,
			//	'is_active' => $nc3IsActive,
			//	'language_id' => $nc3Blog['Blog']['language_id'],
			//	'title' => $nc2Timeframe['Nc2ReservationTimeframe']['title'],
			//	'body1' => $model->convertWYSIWYG($nc2Timeframe['Nc2ReservationTimeframe']['content']),
			//	'body2' => $model->convertWYSIWYG($nc2Timeframe['Nc2ReservationTimeframe']['more_content']),
			//	'publish_start' => $this->convertDate($nc2Timeframe['Nc2ReservationTimeframe']['journal_date']),
			//	'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Timeframe['Nc2ReservationTimeframe']),
			//	'created' => $this->convertDate($nc2Timeframe['Nc2ReservationTimeframe']['insert_time']),
			//	'block_id' => $nc3Blog['Blog']['block_id'],
			//	'title_icon' => $this->convertTitleIcon($nc2Timeframe['Nc2ReservationTimeframe']['icon_name']),
			//],
			//'Block' => [
			//	'id' => $nc3Blog['Blog']['block_id'],
			//	'key' => $nc3BlockKey
			//]
		];
		return $data;
	}

	protected function _convertTimeframeTime($time){
		$hour = substr($time, 0, 2);
		$min = substr($time, 2, 2);
		return $hour . ':' . $min;
	}

	protected function _convertLocationTime($time) {
		return date('Y-m-d H:i:s', strtotime($time));
	}

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
 * Save JournalFrameSetting from Nc2.
 *
 * @param array $nc2Journals Nc2Journal data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3BlogFromNc2($nc2Journals) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog data Migration start.'));

		/* @var $Blog Blog */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Blog = ClassRegistry::init('Blogs.Blog');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

		Current::write('Plugin.key', 'blogs');
		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Blog->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Blog->Behaviors->Block->settings = $Blog->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Journals as $nc2Journal) {
			$Blog->begin();
			try {
				$data = $this->generateNc3BlogData($nc2Journal);
				if (!$data) {
					$Blog->rollback();
					continue;
				}
				$query['conditions'] = [
					'journal_id' => $nc2Journal['Nc2Journal']['journal_id']
				];
				$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('journal_category', $query);
				$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);

				// いる？
				$nc3RoomId = $data['Block']['room_id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$Blog->create();
				$Block->create();
				$Topic->create();

				if (!$Blog->saveBlog($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Blog->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Journal) . "\n" .
						var_export($Blog->validationErrors, true);
					$this->writeMigrationLog($message);
					$Blog->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2JournalId = $nc2Journal['Nc2Journal']['journal_id'];
				$idMap = [
					$nc2JournalId => $Blog->id
				];
				$this->saveMap('Blog', $idMap);

				$nc3Blog = $Blog->findById($Blog->id, 'block_id', null, -1);
				if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $nc3Blog['Blog']['block_id'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Journal);
					$this->writeMigrationLog($message);
					$Blog->rollback();
					continue;
				}

				$Blog->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Blog->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog data Migration end.'));
		return true;
	}

/**
 * Save BlogFrameSetting from Nc2.
 *
 * @param array $nc2JournalBlocks Nc2ournalBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3BlogFrameSettingFromNc2($nc2JournalBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  BlogFrameSetting data Migration start.'));

		/* @var $BlogFrameSetting BlogFrameSetting */
		/* @var $Frame Frame */
		$BlogFrameSetting = ClassRegistry::init('Blogs.BlogFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2JournalBlocks as $nc2JournalBlock) {
			$BlogFrameSetting->begin();
			try {
				$data = $this->generateNc3BlogFrameSettingData($nc2JournalBlock);
				if (!$data) {
					$BlogFrameSetting->rollback();
					continue;
				}

				$BlogFrameSetting->create();
				if (!$BlogFrameSetting->saveBlogFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalBlock) . "\n" .
						var_export($BlogFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$BlogFrameSetting->rollback();
					continue;
				}

				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalBlock) . "\n" .
						var_export($BlogFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$BlogFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $BlogFrameSetting->id
				];
				$this->saveMap('BlogFrameSetting', $idMap);

				$BlogFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::saveBlogFrameSetting()でthrowされるとこの処理に入ってこない
				$BlogFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		/*
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');
		*/

		$this->writeMigrationLog(__d('nc2_to_nc3', '  BlogFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save BlogEntry from Nc2.
 *
 * @param array $nc2JournalPosts Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3BlogEntryFromNc2($nc2JournalPosts) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Entry data Migration start.'));

		/* @var $BlogEntry BlogEntry */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$BlogEntry = ClassRegistry::init('Blogs.BlogEntry');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

		Current::write('Plugin.key', 'blogs');
		//Announcement モデルで	BlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$BlogEntry->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$BlogEntry->Behaviors->Block->settings = $BlogEntry->actsAs['Blocks.Block'];

		//$Nc2Journal = $this->getNc2Model('journal');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2JournalPosts as $nc2JournalPost) {
			$BlogEntry->begin();
			try {
				$data = $this->generateNc3BlogEntryData($nc2JournalPost);
				if (!$data) {
					$BlogEntry->rollback();
					continue;
				}
				$nc3BlockId = $data['Block']['id'];
				$nc2CategoryId = $nc2JournalPost['Nc2JournalPost']['category_id'];
				$data['BlogEntry']['category_id'] = $Nc2ToNc3Category->getNc3CategoryId($nc3BlockId, $nc2CategoryId);

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($nc3BlockId, null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block.id', $nc3BlockId);
				Current::write('Room.id', $nc3RoomId);

				$BlocksLanguage->create();
				$BlogEntry->create();
				$Block->create();
				$Topic->create();

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				$nc3Status = $data['BlogEntry']['status'];
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = ($nc3Status != 2);

				// Hash::merge で BlogEntry::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Blogs/blob/3.1.0/Model/BlogEntry.php#L138-L141
				$BlogEntry->validate = [];

				if (!$BlogEntry->saveEntry($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$BlogEntry->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2JournalPost) . "\n" .
						var_export($BlogEntry->validationErrors, true);
					$this->writeMigrationLog($message);
					$BlogEntry->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
				$idMap = [
					$nc2PostId => $BlogEntry->id
				];
				$this->saveMap('BlogEntry', $idMap);
				$BlogEntry->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$BlogEntry->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Block.id');
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Entry data Migration end.'));
		return true;
	}

/**
 * Save ContentComment from Nc2.
 *
 * @param array $nc2JournalPosts Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3ContentCommentFromNc2($nc2JournalPosts) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Content Comment data Migration start.'));

		/* @var $ContentComment ContentComment */
		/* @var $Nc2ToNc3Comment Nc2ToNc3ContentComment */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$ContentComment = ClassRegistry::init('ContentComments.ContentComment');
		$Nc2ToNc3Comment = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3ContentComment');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		foreach ($nc2JournalPosts as $nc2JournalPost) {
			$ContentComment->begin();
			try {
				$data = $this->generateNc3ContentCommentData($nc2JournalPost);
				if (!$data) {
					$ContentComment->rollback();
					continue;
				}

				$nc2RoomId = $nc2JournalPost['Nc2JournalPost']['room_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomId);
				$nc3RoomId = $mapIdList[$nc2RoomId];
				$nc3Status = $data['ContentComment']['status'];

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = ($nc3Status != 2);

				$ContentComment->create();
				// 一応Model::validatの初期化
				$ContentComment->validate = [];

				if (!$ContentComment->saveContentComment($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalPost) . "\n" .
						var_export($ContentComment->validationErrors, true);
					$this->writeMigrationLog($message);

					$ContentComment->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
				$idMap = [
					$nc2PostId => $ContentComment->id
				];
				if (!$Nc2ToNc3Comment->saveContentCommentMap($idMap, $data['ContentComment']['block_key'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2JournalPost);
					$this->writeMigrationLog($message);

					$ContentComment->rollback();
					continue;
				}

				$ContentComment->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				$ContentComment->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Content Comment Migration end.'));

		return true;
	}

}