<?php
/**
 * Nc2ToNc3PhotoAlbum
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('PhotoAlbumsComponent', 'PhotoAlbums.Controller/Component');
App::uses('ComponentCollection', 'Controller');

/**
 * Nc2ToNc3PhotoAlbum
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
 * @see Nc2ToNc3PhotoAlbumBehavior
 * @method string getLogArgument($nc2PhotoalbumBlock)
 * @method array generateNc3PhotoAlbumData($frameMap, $nc2PhotoalbumAlbum, $nc2Photos)
 * @method array generateNc3PhotoAlbumFrameSettingData($data, $frameMap, $nc2PhotoalbumBlock)
 * @method array generateNc3PhotoData($PhotoAlbumData, $nc2Photo)
 *
 */
class Nc2ToNc3PhotoAlbum extends Nc2ToNc3AppModel {

/**
 * NC3のダミーのフレームキー。NC2でモジュール未配置の場合に一時的に利用する
 *
 * @var string
 */
	const DUMMY_FRAME_KEY = 'Nc2toNc3Dummy';

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
		'Nc2ToNc3.Nc2ToNc3PhotoAlbum',
		'Nc2ToNc3.Nc2ToNc3BlockRolePermission',
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 * @throws Exception
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'PhotoAlbum Migration start.'));

		/* @var $Nc2PhotoalbumBlock AppModel */
		$Nc2PhotoalbumBlock = $this->getNc2Model('photoalbum_block');
		$nc2PhotoalbumBlocks = $Nc2PhotoalbumBlock->find('all');
		if (!$this->__savePhotoAlbumFrameSettingFromNc2($nc2PhotoalbumBlocks)) {
			return false;
		}

		/* @var $Nc2Photoalbum AppModel */
		$Nc2Photoalbum = $this->getNc2Model('photoalbum');
		$nc2Photoalbums = $Nc2Photoalbum->find('all');
		$nc2PhotoalbumIdList = $this->__getNc2PhotoalbumIdList($nc2Photoalbums);
		unset($nc2Photoalbums);
		//$Nc2PhotoalbumAlbum = $this->getNc2Model('photoalbum_album');
		//$nc2PhotoalbumAlbums = $Nc2PhotoalbumAlbum->find('all');
		//if (!$this->__savePhotoAlbumFromNc2($nc2PhotoalbumAlbums)) {
		if (!$this->__savePhotoAlbumFromNc2($nc2PhotoalbumIdList)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'PhotoAlbum Migration end.'));

		return true;
	}

/**
 * Save PhotoAlbumFrameSetting from Nc2.
 *
 * @param array $nc2PhotoalbumBlocks Nc2PhotoalbumBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __savePhotoAlbumFrameSettingFromNc2($nc2PhotoalbumBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbumFrameSetting data Migration start.'));

		/* @var $PhotoAlbum PhotoAlbum */
		/* @var $FrameSetting PhotoAlbumFrameSetting */
		/* @var $PhotoAlbumsComponent PhotoAlbumsComponent */
		/* @var $Nc2Photoalbum AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Block Block */
		$PhotoAlbum = ClassRegistry::init('PhotoAlbums.PhotoAlbum');
		$FrameSetting = ClassRegistry::init('PhotoAlbums.PhotoAlbumFrameSetting');
		$PhotoAlbumSetting = ClassRegistry::init('PhotoAlbums.PhotoAlbumSetting');
		$PhotoAlbumsComponent = new PhotoAlbumsComponent(new ComponentCollection());
		$Frame = ClassRegistry::init('Frames.Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach ($nc2PhotoalbumBlocks as $nc2PhotoalbumBlock) {
			$PhotoAlbum->begin();
			try {

				$nc2BlockId = $nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2PhotoalbumBlock));
					$this->writeMigrationLog($message);
					$PhotoAlbum->rollback();
					continue;
				}
				$frame = $Frame->findById($frameMap['Frame']['id'], null, null, -1);
				$nc3RoomId = $frameMap['Frame']['room_id'];
				Current::write('Frame', $frame['Frame']);
				Current::write('Room.id', $nc3RoomId);
				$Block->create();
				$BlocksLanguage->create();
				$PhotoAlbumSetting->create(false);

				$PhotoAlbumsComponent->initializeSetting();
				$nc3Block = $Block->findByRoomIdAndPluginKey(
					$nc3RoomId,
					'photo_albums',
					['key'],
					null,
					-1
				);
				if (!$this->__saveBlockRolePermission(
					$nc3RoomId,
					$nc3Block['Block']['key'],
					$nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['room_id'])
				) {
					$PhotoAlbum->rollback();
					continue;
				}

				$frameSetting = $FrameSetting->read();
				$data = [
					'PhotoAlbumFrameSetting' => $frameSetting['PhotoAlbumFrameSetting'],
				];

				$data = $this->generateNc3PhotoAlbumFrameSettingData($data, $frameMap, $nc2PhotoalbumBlock);
				if (!$data) {
					$PhotoAlbum->rollback();
					continue;
				}

				$this->__writeCurrent($frameMap, 'photo_albums', $nc3RoomId);

				$FrameSetting->validate = [];
				if (!$FrameSetting->savePhotoAlbumFrameSetting($data)) {
					$message = $this->getLogArgument($nc2PhotoalbumBlock) . "\n" .
						var_export($FrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$PhotoAlbum->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$idMap = [
					$nc2BlockId => $FrameSetting->id,
				];
				$this->saveMap('PhotoAlbumFrameSetting', $idMap);

				$PhotoAlbum->commit();

			} catch (Exception $ex) {
				$PhotoAlbum->rollback($ex);
				throw $ex;
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbumFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save PhotoAlbum from Nc2.
 *
 * PHPMD.ExcessiveMethodLengthに引っかかるが、PhotoAlbum::saveAlbumForAddとPhotoAlbumPhoto::savePhotoを
 * 分けたいので、とりあえずSuppressWarningsの定義で回避
 *
 * @param array $nc2PhotoalbumIdList [[nc3 room_id][] = photoalbum_id]
 * @return bool True on success
 * @throws Exception
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
	private function __savePhotoAlbumFromNc2($nc2PhotoalbumIdList) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbum data Migration start.'));

		/* @var $PhotoAlbum PhotoAlbum */
		/* @var $PhotoAlbumPhoto PhotoAlbumPhoto */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Nc2PhotoalbumBlock AppModel */
		/* @var $Nc2PhotoalbumPhoto AppModel */
		/* @var $Nc2Photoalbum AppModel */
		$PhotoAlbum = ClassRegistry::init('PhotoAlbums.PhotoAlbum');
		$PhotoAlbumPhoto = ClassRegistry::init('PhotoAlbums.PhotoAlbumPhoto');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Nc2PhotoalbumBlock = $this->getNc2Model('photoalbum_block');
		$Nc2PhotoalbumPhoto = $this->getNc2Model('photoalbum_photo');
		$Nc2PhotoalbumAlbum = $this->getNc2Model('photoalbum_album');

		foreach ($nc2PhotoalbumIdList as $nc3RoomId => $nc2PhotoalbumIds) {
			// フォトアルバムはNC2からNC3でデータの持ち方が変わった。
			// nc2 [新規フォトアルバム1][新規アルバム1][写真]
			// nc3 [新規フォトアルバム1][写真]
			// このため、移行時にnc2のアルバムを1つに纏める必要があるため、ルーム単位で纏める。
			$nc2PhotoalbumAlbums = $Nc2PhotoalbumAlbum->find('all', [
				'recursive' => -1,
				'conditions' => [
					'photoalbum_id' => $nc2PhotoalbumIds
				],
			]);

			foreach ($nc2PhotoalbumAlbums as $nc2PhotoalbumAlbum) {
				$PhotoAlbum->begin();
				try {
					$frameMap = [];
					// 最初のblock_idを取得。残りは後処理の__saveDisplayAlbumで登録する。
					$nc2PhotoalbumBlock = $Nc2PhotoalbumBlock->find('first', [
						'conditions' => [
							'photoalbum_id' => $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['photoalbum_id'],
							'display_album_id' => ['0', $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_id']]
						],
						'fields' => 'block_id',
						'order' => 'block_id',
						'recursive' => -1,
					]);
					if ($nc2PhotoalbumBlock) {
						$frameMap = $Nc2ToNc3Frame->getMap($nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['block_id']);
					}

					$data = $this->generateNc3PhotoAlbumData($frameMap, $nc2PhotoalbumAlbum, $nc3RoomId);
					if (!$data) {
						$PhotoAlbum->rollback();
						continue;
					}

					//$this->writeCurrent($frameMap, 'photo_albums');
					$this->__writeCurrent($frameMap, 'photo_albums', $nc3RoomId);

					if (!($data = $this->__savePhotoAlbumSetting(
						$nc3RoomId,
						$data,
						$nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['room_id']))
					) {
						$message = $this->getLogArgument($nc2PhotoalbumAlbum);
						$this->writeMigrationLog($message);

						$PhotoAlbum->rollback();
						continue;
					}
					Current::write('Block.id', $data['Block']['id']);

					$PhotoAlbum->create();
					$PhotoAlbum->validate = [];
					if (!$PhotoAlbum->saveAlbumForAdd($data)) {
						$message = $this->getLogArgument($nc2PhotoalbumAlbum) . "\n" .
							var_export($PhotoAlbum->validationErrors, true);
						$this->writeMigrationLog($message);

						$PhotoAlbum->rollback();
						continue;
					}

					$nc3PhotoAlbum = $PhotoAlbum->read();
					if (!$this->__saveDisplayAlbum($nc2PhotoalbumAlbum, $nc3PhotoAlbum)) {
						$message = $this->getLogArgument($nc2PhotoalbumAlbum);
						$this->writeMigrationLog($message);

						$PhotoAlbum->rollback();
						continue;
					}

					$nc2AlbumId = $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_id'];
					$nc2Photos = $Nc2PhotoalbumPhoto->findAllByAlbumId($nc2AlbumId, null, ['photo_sequence' => 'ASC'], -1);
					foreach ($nc2Photos as $nc2Photo) {
						$data = $this->generateNc3PhotoData($nc3PhotoAlbum['PhotoAlbum'], $nc2Photo);
						$PhotoAlbumPhoto->create();
						$PhotoAlbumPhoto->validate = [];
						if (!$PhotoAlbumPhoto->savePhoto($data)) {
							$message = $this->getLogArgument($nc2Photo) . "\n" .
								var_export($PhotoAlbumPhoto->validationErrors, true);
							$this->writeMigrationLog($message);

							continue;
						}
					}

					// 登録処理で使用しているデータを空に戻す
					//unset(Current::$permission[$frameMap['Frame']['room_id']]['Permission']['content_publishable']['value']);
					unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

					$idMap = [
						$nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_id'] => $PhotoAlbum->id,
					];
					$this->saveMap('PhotoAlbum', $idMap);

					$PhotoAlbum->commit();

				} catch (Exception $ex) {
					$PhotoAlbum->rollback($ex);
					throw $ex;
				}
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbum data Migration end.'));

		return true;
	}

/**
 * __getNc2PhotoalbumIdList
 *
 * @param array $nc2Photoalbums Nc2Photoalbum data.
 * @return array [[nc3 room_id][] = photoalbum_id]
 */
	private function __getNc2PhotoalbumIdList($nc2Photoalbums) {
		/* @see Nc2ToNc3Map::getMapIdList() */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapRoomIdList = $Nc2ToNc3Map->getMapIdList('Room');

		//$nc2PhotoalbumIdList [[nc3 room_id][] = photoalbum_id]
		$nc2PhotoalbumIdList = [];
		foreach ($nc2Photoalbums as $nc2Photoalbum) {
			$nc2RoomId = $nc2Photoalbum['Nc2Photoalbum']['room_id'];
			// nc3 room_id取得
			if (! isset($mapRoomIdList[$nc2RoomId])) {
				// 基本ありえない想定
				$message = __d('nc2_to_nc3', '%s No room ID corresponding to nc3',
					'nc2_room_id:' . $nc2RoomId);
				$this->writeMigrationLog($message);
				continue;
			}
			$nc3RoomId = $mapRoomIdList[$nc2RoomId];
			$nc2PhotoalbumIdList[$nc3RoomId][] = $nc2Photoalbum['Nc2Photoalbum']['photoalbum_id'];
		}
		return $nc2PhotoalbumIdList;
	}

/**
 * Save PhotoAlbumSetting.
 *
 * @param string $nc3RoomId nc3 room id.
 * @param array $nc3PhotoAlbum data.
 * @param array $nc2RoomId nc2 room id.
 * @return array Nc3PhotoAlbum data.
 */
	private function __savePhotoAlbumSetting($nc3RoomId, $nc3PhotoAlbum, $nc2RoomId) {
		/* @var $Block Block */
		$Block = ClassRegistry::init('Blocks.Block');
		$PhotoAlbumSetting = ClassRegistry::init('PhotoAlbums.PhotoAlbumSetting');

		$nc3Block = $Block->findByRoomIdAndPluginKey(
			$nc3RoomId,
			'photo_albums',
			['id', 'key'],
			null,
			-1
		);

		if (!$nc3Block) {
			$PhotoAlbumSetting->create(false);
			$Block->create(false);
			$data = $PhotoAlbumSetting->createBlockSetting();
			if (!$PhotoAlbumSetting->savePhotoAlbumSetting($data)) {
				return [];
			}

			$nc3Block = $Block->findByRoomIdAndPluginKey(
				$nc3RoomId,
				'photo_albums',
				['id', 'key'],
				null,
				-1
			);

			if (!$this->__saveBlockRolePermission($nc3RoomId, $nc3Block['Block']['key'], $nc2RoomId)) {
				return [];
			}
		}

		$nc3PhotoAlbum['Block'] = [
			'id' => $nc3Block['Block']['id'],
			'key' => $nc3Block['Block']['key'],
			'room_id' => $nc3RoomId,
			'plugin_key' => 'photo_albums',
			'public_type' => 1,
		];
		$nc3PhotoAlbum['PhotoAlbum']['block_id'] = $nc3Block['Block']['id'];

		return $nc3PhotoAlbum;
	}

/**
 * Save BlockRolePermission.
 *
 * @param string $nc3RoomId nc3 room id.
 * @param array $nc2RoomId nc2 room id.
 * @return array Nc3PhotoAlbum data.
 */
	private function __saveBlockRolePermission($nc3RoomId, $nc3BlockKey, $nc2RoomId) {
		$Nc2Photoalbum = $this->getNc2Model('photoalbum');
		$PhotoAlbumSetting = ClassRegistry::init('PhotoAlbums.PhotoAlbumSetting');

		$nc2Photoalbum = $Nc2Photoalbum->findByRoomId(
			$nc2RoomId,
			'album_authority',
			'album_authority',
			-1
		);
		$data = [
			'PhotoAlbumSetting' => ['id' => null],
		];
		$data = Hash::merge($data, $this->makeContentPermissionData(
			$nc2Photoalbum['Nc2Photoalbum']['album_authority'], $nc3RoomId));
		unset($data['BlockRolePermission']['content_comment_publishable'],
			$data['BlockRolePermission']['content_comment_creatable']);
		foreach ($data['BlockRolePermission']['content_creatable'] as &$role) {
			$role['block_key'] = $nc3BlockKey;
		}

		return $PhotoAlbumSetting->savePhotoAlbumSetting($data);
	}

/**
 * Save DisplayAlbum.
 *
 * @param array $nc2PhotoalbumAlbum data.
 * @param array $nc3PhotoAlbum data.
 * @return bool
 */
	private function __saveDisplayAlbum($nc2PhotoalbumAlbum, $nc3PhotoAlbum) {
		$DisplayAlbum = ClassRegistry::init('PhotoAlbums.PhotoAlbumDisplayAlbum');
		if (Current::read('Frame.key') == self::DUMMY_FRAME_KEY) {
			// $PhotoAlbum->saveAlbumForAdd($data)では、新規登録時に$PhotoAlbumDisplayAlbumが必須で登録される。
			// ダミーで登録したFrameKeyのデータを削除
			$conditions = array('frame_key' => self::DUMMY_FRAME_KEY);
			$DisplayAlbum->deleteAll($conditions, false);
		}

		if ($nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['public_flag'] === '0' ) {
			$conditions = [
				'frame_key' => Current::read('Frame.key'),
				'album_key' => $nc3PhotoAlbum['PhotoAlbum']['key']
			];
			$DisplayAlbum->deleteAll($conditions, false);

			// 非公開はどのフレームにも表示しない
			return true;
		}

		$Nc2PhotoalbumBlock = $this->getNc2Model('photoalbum_block');
		$nc2PhotoalbumBlocks = $Nc2PhotoalbumBlock->find('all', [
			'conditions' => [
				'photoalbum_id' => $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['photoalbum_id'],
				'display_album_id' => ['0', $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_id']]
			],
			'fields' => 'block_id',
			'order' => 'block_id',
			'recursive' => -1,
		]);
		// 先頭のblock_idは前処理で登録済み
		array_shift($nc2PhotoalbumBlocks);

		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach($nc2PhotoalbumBlocks as $nc2PhotoalbumBlock) {
			$frameMap = $Nc2ToNc3Frame->getMap($nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['block_id']);
			if ($frameMap) {
				$displayAlbum = [
					'frame_key' => $frameMap['Frame']['key'],
					'album_key' => $nc3PhotoAlbum['PhotoAlbum']['key']
				];
				$DisplayAlbum->create($displayAlbum);
				if (!$DisplayAlbum->save()) {
					return false;
				}
			}
		}

		return true;
	}

/**
 * Write Current.
 *
 * @param array $frameMap array data.
 * @param string $pluginKey plugin key.
 * @param string $nc3RoomId nc3 room id.
 * @return void
 * @see Nc2ToNc3BaseBehavior::_writeCurrent()からコピー
 */
	private function __writeCurrent($frameMap, $pluginKey, $nc3RoomId) {
		if ($frameMap) {
			Current::write('Frame.key', $frameMap['Frame']['key']);
		} else {
			Current::write('Frame.key', self::DUMMY_FRAME_KEY);
		}
		Current::write('Frame.room_id', $nc3RoomId);
		Current::write('Frame.plugin_key', $pluginKey);
		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
		Current::write('Plugin.key', $pluginKey);
		// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
		Current::write('Room.id', $nc3RoomId);
		Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;
	}
}

