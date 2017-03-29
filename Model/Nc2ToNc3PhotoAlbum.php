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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3PhotoAlbum'];

/**
 * Migration method.
 *
 * @return bool True on success.
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
		$Nc2PhotoalbumAlbum = $this->getNc2Model('photoalbum_album');
		$nc2PhotoalbumAlbums = $Nc2PhotoalbumAlbum->find('all');
		if (!$this->__savePhotoAlbumFromNc2($nc2PhotoalbumAlbums)) {
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
		$PhotoAlbumsComponent = new PhotoAlbumsComponent(new ComponentCollection());
		$Frame = ClassRegistry::init('Frames.Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach ($nc2PhotoalbumBlocks as $nc2PhotoalbumBlock) {
			$PhotoAlbum->begin();
			try {

				$nc2BlockId = $nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				$frame = $Frame->findById($frameMap['Frame']['id'], null, null, -1);
				$nc3RoomId = $frameMap['Frame']['room_id'];
				Current::write('Frame', $frame['Frame']);
				Current::write('Room.id', $nc3RoomId);
				$Block->create();
				$PhotoAlbumsComponent->initializeSetting();
				$frameSetting = $FrameSetting->read();
				$data = [
					'PhotoAlbumFrameSetting' => $frameSetting['PhotoAlbumFrameSetting'],
				];
//				error_log(print_r('fddsdfdfs', true)."\n\n", 3, LOGS."/tail.log");
//				error_log(print_r($frameSetting, true)."\n\n", 3, LOGS."/tail.log");

				$data = $this->generateNc3PhotoAlbumFrameSettingData($data, $frameMap, $nc2PhotoalbumBlock);
				if (!$data) {
					$PhotoAlbum->rollback();
					continue;
				}

				Current::write('Frame.key', $frameMap['Frame']['key']);
				Current::write('Frame.room_id', $nc3RoomId);
				Current::write('Frame.plugin_key', 'photo_albums');

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
				Current::write('Plugin.key', 'photo_albums');

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$FrameSetting->validate = [];
				if (!$FrameSetting->savePhotoAlbumFrameSetting($data)) {
					$message = $this->getLogArgument($nc2PhotoalbumBlock) . "\n" .
						var_export($FrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$PhotoAlbum->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

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

		$this->__removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbumFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save PhotoAlbum from Nc2.
 *
 * @param array $nc2PhotoalbumAlbums Nc2PhotoalbumAlbum data.
 * @return bool True on success
 * @throws Exception
 */
	private function __savePhotoAlbumFromNc2($nc2PhotoalbumAlbums) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbum data Migration start.'));

		/* @var $PhotoAlbum PhotoAlbum */
		/* @var $PhotoAlbumPhoto PhotoAlbumPhoto */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Nc2PhotoalbumBlock AppModel */
		/* @var $Nc2PhotoalbumPhoto AppModel */
		/* @var $Block Block */
		$PhotoAlbum = ClassRegistry::init('PhotoAlbums.PhotoAlbum');
		$PhotoAlbumPhoto = ClassRegistry::init('PhotoAlbums.PhotoAlbumPhoto');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		$Nc2PhotoalbumBlock = $this->getNc2Model('photoalbum_block');
		$Nc2PhotoalbumPhoto = $this->getNc2Model('photoalbum_photo');
		foreach ($nc2PhotoalbumAlbums as $nc2PhotoalbumAlbum) {
			$PhotoAlbum->begin();
			try {
				$nc2PhotoalbumId = $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['photoalbum_id'];
				$nc2PhotoalbumBlock = $Nc2PhotoalbumBlock->findByPhotoalbumId($nc2PhotoalbumId);
				$frameMap = $Nc2ToNc3Frame->getMap($nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['block_id']);

				$nc2AlbumId = $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_id'];
				$nc2Photos = $Nc2PhotoalbumPhoto->findAllByAlbumId($nc2AlbumId, null, ['photo_sequence' => 'ASC'], -1);
				if (count($nc2Photos) === 0) {
					$message = $this->getLogArgument($nc2Photos);
					$this->writeMigrationLog($message);

					$PhotoAlbum->rollback();
					continue;
				}
				$firstPhoto = array_shift($nc2Photos);
				$data = $this->generateNc3PhotoAlbumData($frameMap, $nc2PhotoalbumAlbum, $firstPhoto);
				if (!$data) {
					$PhotoAlbum->rollback();
					continue;
				}

				$nc3Block = $Block->findByRoomIdAndPluginKey(
					$frameMap['Frame']['room_id'],
					'photo_albums',
					null,
					null,
					-1
				);
				$data['Block'] = $nc3Block['Block'];
				$data['PhotoAlbum']['block_id'] = $nc3Block['Block']['id'];
				Current::write('Block.id', $nc3Block['Block']['id']);

				$this->writeCurrent($frameMap, 'photo_albums');

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
				foreach ($nc2Photos as $nc2Photo) {
					$data = $this->generateNc3PhotoData($nc3PhotoAlbum['PhotoAlbum'], $nc2Photo);
					$PhotoAlbumPhoto->create();
					$PhotoAlbumPhoto->validate = [];
					if (!$PhotoAlbumPhoto->savePhoto($data)) {
						$message = $this->getLogArgument($nc2Photo) . "\n" .
							var_export($PhotoAlbumPhoto->validationErrors, true);
						$this->writeMigrationLog($message);

						$PhotoAlbumPhoto->rollback();
						continue;
					}
				}

				// 登録処理で使用しているデータを空に戻す
				unset(CurrentBase::$permission[$frameMap['Frame']['room_id']]['Permission']['content_publishable']['value']);

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

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  PhotoAlbum data Migration end.'));

		return true;
	}
}

