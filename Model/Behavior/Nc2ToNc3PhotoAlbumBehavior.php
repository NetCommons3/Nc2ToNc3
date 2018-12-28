<?php
/**
 * Nc2ToNc3PhotoAlbumBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3PhotoAlbumBehavior
 *
 */
class Nc2ToNc3PhotoAlbumBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Photoalbum Array data of Nc2Photoalbum or Nc2PhotoalbumAlbum or Nc2PhotoalbumPhoto.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Photoalbum) {
		return $this->__getLogArgument($nc2Photoalbum);
	}

/**
 * Generate Nc3PhotoAlbum data.
 *
 * Data sample
 * data[Frame][id]:
 * data[Block][id]:0
 * data[Block][key]:0
 * data[Block][room_id]:0
 * data[Block][plugin_key]:0
 * data[Block][public_type]:0
 * data[PhotoAlbum][id]:
 * data[PhotoAlbum][key]:
 * data[PhotoAlbum][language_id]:
 * data[PhotoAlbum][status]:
 * data[PhotoAlbum][name]:
 * data[PhotoAlbum][description]:
 * data[PhotoAlbum][selectedJacketIndex]:
 * data[PhotoAlbum][jacket]:
 * data[PhotoAlbum][created_user]:
 * data[PhotoAlbum][created]:
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap FrameMap data.
 * @param array $nc2PhotoalbumAlbum Nc2PhotoalbumAlbum data.
 * @param array $nc2Photo Nc2PhotoalbumPhoto data.
 * @param array $nc3RoomId nc3 room id.
 * @return array Nc3PhotoAlbum data.
 */
	public function generateNc3PhotoAlbumData(Model $model, $frameMap, $nc2PhotoalbumAlbum, $nc2Photo, $nc3RoomId) {
		/* @var $Block Block */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Block = ClassRegistry::init('Blocks.Block');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2AlbumId = $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('PhotoAlbum', $nc2AlbumId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$nc3Block = $Block->findByRoomIdAndPluginKey(
			//$frameMap['Frame']['room_id'],
			$nc3RoomId,
			'photo_albums',
			['id', 'key'],
			null,
			-1
		);
		if (!$nc3Block) {
			// 現状、nc2ルーム内に未配置だとnc3ブロックデータが作成されない移行プログラムなため、ここで返す
			// そこまで追えなかった
			$message = __d('nc2_to_nc3', '%s does not migration. Not placed in nc2 room', $this->__getLogArgument($nc2PhotoalbumAlbum));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		/* @var $Nc2ToNc3Upload Nc2ToNc3Upload */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$Nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
		$nc2UploadId = $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['upload_id'];
		if ($nc2UploadId !== '0') {
			$jacket = $Nc2ToNc3Upload->generateUploadFile($nc2UploadId);
		} else {
			$nc2Jacket = $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_jacket'];
			$jacket = $this->__generatePresetFile($nc2Jacket);
		}
		$nc3Photo = $Nc2ToNc3Upload->generateUploadFile($nc2Photo['Nc2PhotoalbumPhoto']['upload_id']);

		if ($frameMap) {
			// フレームがあったらセット
			$data['Frame'] = [
				'id' => $frameMap['Frame']['id'],
			];
		}
		$data['Block'] = [
			'id' => $nc3Block['Block']['id'],
			'key' => $nc3Block['Block']['key'],
			//'room_id' => $frameMap['Frame']['room_id'],
			'room_id' => $nc3RoomId,
			'plugin_key' => 'photo_albums',
			'public_type' => 1,
		];
		$data['PhotoAlbum'] = [
			'id' => '',
			'key' => '',
			'language_id' => '',
			'status' => '1',
			'name' => $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_name'],
			'description' => $nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['album_description'],
			'selectedJacketIndex' => '0',
			'jacket' => $jacket,
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']),
			'created' => $this->_convertDate($nc2PhotoalbumAlbum['Nc2PhotoalbumAlbum']['insert_time']),
		];
		$data['PhotoAlbumPhoto'] = [['photo' => $nc3Photo]];

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
		$data['Topic'] = [
			'plugin_key' => 'photo_albums',
		];

		return $data;
	}

/**
 * Generate Nc3PhotoAlbumPhoto data.
 *
 * Data sample
 * data[PhotoAlbumPhoto][id]:
 * data[PhotoAlbumPhoto][key]:
 * data[PhotoAlbumPhoto][album_key]:
 * data[PhotoAlbumPhoto][language_id]:
 * data[PhotoAlbumPhoto][block_id]:
 * data[PhotoAlbumPhoto][status]:
 * data[PhotoAlbumPhoto][description]:
 * data[PhotoAlbumPhoto][photo]:
 * data[PhotoAlbumPhoto][created_user]:
 * data[PhotoAlbumPhoto][created]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3PhotoAlbum Nc3PhotoAlbum data.
 * @param array $nc2Photo Nc2Photo data.
 * @return array Nc3PhotoAlbumPhoto data.
 */
	public function generateNc3PhotoData(Model $model, $nc3PhotoAlbum, $nc2Photo) {
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc2UploadId = $nc2Photo['Nc2PhotoalbumPhoto']['upload_id'];
		$nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
		$nc3PhotoFile = $nc2ToNc3Upload->generateUploadFile($nc2UploadId);

		$data['PhotoAlbumPhoto'] = [
			'id' => '',
			'key' => '',
			'album_key' => $nc3PhotoAlbum['key'],
			'language_id' => '',
			'block_id' => $nc3PhotoAlbum['block_id'],
			'status' => '1',
			'description' => $nc2Photo['Nc2PhotoalbumPhoto']['photo_description'],
			'photo' => $nc3PhotoFile,
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Photo['Nc2PhotoalbumPhoto']),
			'created' => $this->_convertDate($nc2Photo['Nc2PhotoalbumPhoto']['insert_time']),
		];

		return $data;
	}

/**
 * Generate Nc3PhotoAlbumFrameSetting data.
 *
 * Data sample
 * data[PhotoAlbumFrameSetting][frame_key]:
 * data[PhotoAlbumFrameSetting][display_type]:
 * data[PhotoAlbumFrameSetting][slide_height]:
 * data[PhotoAlbumFrameSetting][albums_order]:
 * data[PhotoAlbumFrameSetting][albums_per_page]:
 * data[PhotoAlbumFrameSetting][photos_order]:
 * data[PhotoAlbumFrameSetting][photos_per_page]:
 * data[PhotoAlbumFrameSetting][created_user]:
 * data[PhotoAlbumFrameSetting][created]:
 *
 * @param Model $model Model using this behavior.
 * @param array $data array.
 * @param array $frameMap Frame mapping data.
 * @param array $nc2PhotoalbumBlock Nc2PhotoalbumBlock data.
 * @return array Nc3PhotoAlbumFrameSetting data.
 */
	public function generateNc3PhotoAlbumFrameSettingData(Model $model, $data, $frameMap, $nc2PhotoalbumBlock) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2BlockId = $nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['block_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('PhotoAlbumFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		// display_type更新
		$displayType = $nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['display'];
		switch ($displayType) {
			case '0':
				$displayType = PhotoAlbumFrameSetting::DISPLAY_TYPE_ALBUMS;
				break;
			case '1':
				$displayType = PhotoAlbumFrameSetting::DISPLAY_TYPE_SLIDE;
				break;
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$photoAlbumFrameSet['PhotoAlbumFrameSetting'] = [
			'frame_key' => $frameMap['Frame']['key'],
			'display_type' => $displayType,
			'slide_height' => $nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['height'],
			'albums_order' => 'PhotoAlbum.modified desc',
			'albums_per_page' => $nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['album_visible_row'],
			'photos_order' => 'PhotoAlbumPhoto.modified desc',
			'photos_per_page' => '50',
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2PhotoalbumBlock['Nc2PhotoalbumBlock']),
			'created' => $this->_convertDate($nc2PhotoalbumBlock['Nc2PhotoalbumBlock']['insert_time']),
		];
		$data += $photoAlbumFrameSet;

		return $data;
	}

/**
 * Generate Nc3Upload data for Files.AttachmentBehavior.
 * Copy target file to temporary folder.
 *
 * @param string $nc2Jacket Nc2PhotoalbumAlbum.album_jacket.
 * @return array jacket data
 */
	private function __generatePresetFile($nc2Jacket) {
		$data = [];

		$presetArr = [
			'animal.gif', 'animal2.gif', 'city.gif', 'event.gif', 'event2.gif', 'flower.gif', 'food.gif',
			'hobby.gif', 'human.gif', 'nature.gif', 'nature2.gif', 'room.gif', 'scene.gif', 'sports.gif', 'travel.gif',
		];
		if (!in_array($nc2Jacket, $presetArr)) {
			return $data;
		}

		$tmpName = CakePlugin::path('Nc2ToNc3') . 'webroot' . DS . 'images' . DS . 'photo_albums' . DS . $nc2Jacket;

		if (!is_readable($tmpName)) {
			$message = __d('nc2_to_nc3', '%s not found .', 'Nc2PhotoalbumAlbum album_jacket:' . $nc2Jacket);
			$this->_writeMigrationLog($message);

			return $data;
		}

		// コマンド実行時アップロードファイルのパスが実行パス配下になるのでとりあえずここでchdir
		// @see https://github.com/NetCommons3/Files/blob/3.0.1/Model/UploadFile.php#L172-L179
		chdir(WWW_ROOT);

		// アップロード処理で削除されるので一時フォルダーにコピー
		// @see https://github.com/josegonzalez/cakephp-upload/blob/1.3.1/Model/Behavior/UploadBehavior.php#L337
		$Folder = new TemporaryFolder();
		copy($tmpName, $Folder->path . DS . $nc2Jacket);

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$data = [
			'name' => $nc2Jacket,
			'type' => $finfo->file($tmpName),
			'tmp_name' => $Folder->path . DS . $nc2Jacket,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($tmpName),
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Photoalbum Array data of Nc2Photoalbum or Nc2PhotoalbumAlbum or Nc2PhotoalbumPhoto.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Photoalbum) {
		if (!isset($nc2Photoalbum['Nc2PhotoalbumBlock']['block_id'])) {
			return 'Nc2PhotoalbumBlock' .
				'block_id' . $nc2Photoalbum['Nc2PhotoalbumBlock']['block_id'];
		}

		if (isset($nc2Photoalbum['Nc2Photoalbum'])) {
			return 'Nc2Photoalbum ' .
				'photoalbum_id:' . $nc2Photoalbum['Nc2Photoalbum']['photoalbum_id'];
		}

		if (isset($nc2Photoalbum['Nc2PhotoalbumAlbum'])) {
			return 'Nc2PhotoalbumAlbum ' .
				'album_id:' . $nc2Photoalbum['Nc2PhotoalbumAlbum']['album_id'];
		}

		return 'Nc2PhotoalbumPhoto ' .
			'photo_id:' . $nc2Photoalbum['Nc2PhotoalbumPhoto']['photo_id'];
	}
}
