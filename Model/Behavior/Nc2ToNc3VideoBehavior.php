<?php
/**
 * Nc2ToNc3VideoBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3VideoBehavior
 *
 */

class Nc2ToNc3VideoBehavior extends Nc2ToNc3BaseBehavior {
/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Video Array data of Nc2Video, Nc2MultimediaItem.
 * @return string Log argument
 */

	public function getLogArgument(Model $model, $nc2Video) {
		return $this->__getLogArgument($nc2Video);
	}

/**
 * Generate Nc3VideoSetting data.
 *
 * Data sample
 * data[Frame][id]:15data[Block][id]:
 * data[Block][key]:
 * data[BlocksLanguage][language_id]:2
 * data[Block][room_id]:5
 * data[Block][plugin_key]:blocks
 * data[VideoSetting][id]:
 * data[VideoSetting][use_workflow]:0
 * data[VideoSetting][use_comment_approval]:0
 * data[VideoSetting][total_size]:0
 * data[VideoFrameSetting][id]:
 * data[VideoFrameSetting][frame_key]:a1a2bba4d7d359c19c5ef46f41ac4622
 * data[VideoFrameSetting][display_order]:new
 * data[VideoFrameSetting][display_number]:10
 * data[BlocksLanguage][language_id]:2
 * data[BlocksLanguage][name]:NC3動画テスト
 * data[Block][public_type]:1
 * data[Block][publish_start]:
 * data[Block][publish_end]:
 * data[VideoSetting][use_like]:0
 * data[VideoSetting][use_like]:1
 * data[VideoSetting][use_unlike]:0
 * data[VideoSetting][use_unlike]:1
 * data[VideoSetting][use_comment]:0
 * data[VideoSetting][use_comment]:1
 * data[VideoSetting][auto_play]:0
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Multimedia Nc2Multimedia data.
 * @param array $nc2MultimediaBlock Nc2MultimediaBlock data.
 * @return array Nc3VideoSetting data.
 */

	public function generateNc3VideoSettingData(Model $model, $nc2Multimedia, $nc2MultimediaBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2MultimediaBlock['Nc2MultimediaBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2MultimediaBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$nc2MultimediaId = $nc2Multimedia['Nc2Multimedia']['multimedia_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('VideoSetting', $nc2MultimediaId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$data = [];

		$data = [
			'Frame' => [
				'id' => $frameMap['Frame']['id']
			],
			'Block' => [
				'id' => '',
				'room_id' => $frameMap['Frame']['room_id'],
				'plugin_key' => 'videos',
				'name' => 'NC2-Videos',
				'public_type' => '1'
			],
			'VideoFrameSetting' => [
				'id' => '',
				'frame_key' => $frameMap['Frame']['key'],
				'display_order' => 'new',
				'display_number' => '10',
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2MultimediaBlock['Nc2MultimediaBlock']),
				'created' => $this->_convertDate($nc2MultimediaBlock['Nc2MultimediaBlock']['insert_time']),
			],
			'BlocksLanguage' => [
				'language_id' => '2', //とりあえず固定値。前提条件の対応必要
				'name' => 'NC2-Videos'
			],
			'VideoSetting' => [
				'total_size' => '', //追加対応必要
				'use_like' => $nc2Multimedia['Nc2Multimedia']['vote_flag'],
				'use_unlike' => '0',
				'use_comment' => $nc2Multimedia['Nc2Multimedia']['comment_flag'],
				'auto_play' => '0' //とりあえず固定値。
			]
		];
		return $data;
	}

/**
 * Generate Nc3Video data.
 *
 * Data sample
 * data[VideoEntry][key]:
 * data[Frame][id]:15
 * data[Block][id]:3
 * data[Video][block_id]:
 * data[Video][title]:aaaa
 * data[Video][description]:<p>aaaaaa</p>
 * data[Video][status]:
 * data[Video][video_file][name]:dnld.mp4
 * data[Video][video_file][type]:video/mp4
 * data[Video][video_file][tmp_name]:
 * data[Video][video_file][error]:
 * data[Video][video_file][size]:
 * data[Block][key]:9873556528b4ac6eaa22e52e28633c94
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2MultimediaItem Nc2MultimediaItem data.
 * @return array Nc3Video data.
 */

	public function generateNc3VideoData(Model $model, $nc2MultimediaItem) {
		$nc2MultimediaItemId = $nc2MultimediaItem['Nc2MultimediaItem']['item_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Video', $nc2MultimediaItemId);
		if ($mapIdList) {
			//	 移行済み
			return [];
		}
		$nc2multimediaRoomId = $nc2MultimediaItem['Nc2MultimediaItem']['room_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2multimediaRoomId);
		$nc3RoomId = $mapIdList[$nc2multimediaRoomId];

		//room_id とpluginキーから対象のBlockのレコードを取得
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Blocks = $Block->findByRoomIdAndPluginKey($nc3RoomId, 'videos', null, null, -1);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		//Videoファイルの準備
		$nc2UploadId = $nc2MultimediaItem['Nc2MultimediaItem']['upload_id'];
		$nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
		$nc3VideoFile = $nc2ToNc3Upload->generateUploadFile($nc2UploadId);

		$data = [];
		$data = [
			'Video' => [
				'block_id' => $nc3Blocks['Block']['id'],
				'category_id' => '', //Nc2ToNc3Categoryクラス追加で対応予定
				'language_id' => '2', //とりあえず固定値。前提条件の対応必要
				'title' => $nc2MultimediaItem['Nc2MultimediaItem']['item_name'],
				'title_icon' => '',
				'video_time' => $nc2MultimediaItem['Nc2MultimediaItem']['duration'],
				'play_number' => $nc2MultimediaItem['Nc2MultimediaItem']['item_play_count'],
				'description' => $nc2MultimediaItem['Nc2MultimediaItem']['item_description'],
				'status' => '1', //保留
				'is_active' => '', //保留。設計書にあるphoto_nameない。
				'is_latest' => '1', //とりあえず固定値。前提条件の対応必要
				'created' => $this->_convertDate($nc2MultimediaItem['Nc2MultimediaItem']['insert_time']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2MultimediaItem['Nc2MultimediaItem']),
				'video_file' => $nc3VideoFile
				],
			'Block' => [
				'id' => $nc3Blocks['Block']['id'],
				'key' => $nc3Blocks['Block']['key'],
			],
		];
		//コメントは、Nc2ToNc3Commentクラス追加で対応予定

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Multimedia Array data of MultimediaBlock, MultimediaItem.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Multimedia) {
		if (isset($nc2MultimediaBlock['Nc2MultimediaBlock'])) {
			return 'Nc2MultimediaBlock ' .
				'multimedia_id:' . $nc2MultimediaBlock['Nc2MultimediaBlock']['multimedia_id'];
		}

		if (isset($nc2MultimediaItem['Nc2MultimediaItem'])) {
			return 'nc2MultimediaItem ' .
				'Item_id:' . $nc2MultimediaItem['Nc2MultimediaItem']['Item_id'];
		}
	}
}