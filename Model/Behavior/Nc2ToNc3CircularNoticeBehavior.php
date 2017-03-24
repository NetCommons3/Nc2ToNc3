<?php
/**
 * Nc2ToNc3CircularNoticeBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3CircularNoticeBehavior
 *
 */
class Nc2ToNc3CircularNoticeBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Circular Array data of Nc2CircularNoticeBlock and Nc2Circular.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Circular) {
		return $this->__getLogArgument($nc2Circular);
	}

/**
 * Generate generate Nc3CircularNoticeFrameSettingData.
 *
 * Data sample
 *
 * data[Frame][id]:28
 * data[Block][id]:
 * data[Block][key]:
 * data[BlocksLanguage][language_id]:
 * data[Block][room_id]:
 * data[Block][plugin_key]:
 * data[Frame][id]:28
 * data[CircularNoticeFrameSetting][id]:1
 * data[CircularNoticeFrameSetting][frame_key]:f165b7a014ccdc0235a18d6e473764cb
 * data[CircularNoticeFrameSetting][display_number]:10
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2CircularBlock Nc2CircularBlock data.
 * @return array Nc3CircularNoticeFrameSetting data.
 *
 */

	public function generateNc3CircularNoticeFrameSettingData(Model $model, $nc2CircularBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Fr	ame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2CircularBlock['Nc2CircularBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2CircularBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CircularNoticeFrameSetting', $nc2BlockId);
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
				'plugin_key' => 'circular_notices',
				'name' => '',
				'public_type' => '1',
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CircularBlock['Nc2CircularBlock'])
			],
			'CircularNoticeFrameSetting' => [
				'id' => '',
				'frame_key' => $frameMap['Frame']['key'],
				'display_number' => $nc2CircularBlock['Nc2CircularBlock']['visible_row'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CircularBlock['Nc2CircularBlock']),
				'created' => $this->_convertDate($nc2CircularBlock['Nc2CircularBlock']['insert_time']),
			],
		];
		return $data;
	}

/**
 * Generate generateNc3CircularNoticeFrameSetting Data.
 *
 * Data sample
 *
 * data[Frame][id]:28
 * data[Block][id]:11
 * data[CircularNoticeContent][id]:
 * data[CircularNoticeContent][language_id]:2
 * data[CircularNoticeContent][circular_notice_setting_key]:a21925c641f18117765816bc68a22870
 * data[CircularNoticeContent][title_icon]:
 * data[CircularNoticeContent][subject]:NC3回覧板のテスト
 * data[CircularNoticeContent][content]:<p>テストですねん</p>
 * data[CircularNoticeContent][reply_type]:1
 * data[CircularNoticeContent][is_room_target]:1
 * data[CircularNoticeTargetUser][0][user_id]:1
 * data[CircularNoticeContent][publish_start]:2017-03-15 12:00
 * data[CircularNoticeContent][publish_end]:
 * data[CircularNoticeContent][use_reply_deadline]:0
 * data[CircularNoticeContent][reply_deadline]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Circular Nc2Circular data.
 * @return array CircularNoticeContent data.
 *
 */
	public function generateNc3CircularNoticeContentData(Model $model, $nc2Circular) {
		$nc2CircularId = $nc2Circular['Nc2Circular']['circular_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CircularNoticeContent', $nc2CircularId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}
		$nc2CircularRoomId = $nc2Circular['Nc2Circular']['room_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2CircularRoomId);
		$nc3RoomId = $mapIdList[$nc2CircularRoomId];

		//room_id とpluginキーから対象のBlockのレコードを取得
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Blocks = $Block->findByRoomIdAndPluginKey($nc3RoomId, 'circular_notices', null, null, -1);

		$CircularNoticeSet = ClassRegistry::init('CircularNotices.CircularNoticeSetting');

		$nc3CircularNoticeSet = $CircularNoticeSet->findByBlockKey($nc3Blocks['Block']['key'], null, null, -1);

		//$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		//$nc3BlocksLanguages = $BlocksLanguage->findByBlockId($nc3Blocks['Block']['id'], null, null, -1);

		/*$nc3CircularNoticeIds = $Nc2ToNc3Map->getMapIdList('CircularNotice', $nc2JournalPost['Nc2JournalPost']['journal_id']);
		$nc3CircularNoticeId = $nc3CircularNoticeIds[$nc2JournalPost['Nc2JournalPost']['journal_id']];

		$CircularNotice = ClassRegistry::init('CircularNotices.CircularNotice');
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3CircularNotice = $CircularNotice->findById($nc3CircularNoticeId, null, null, -1);
		$Blocks = $Block->findById($nc3CircularNotice['CircularNotice']['block_id'], null, null, -1);
		$nc3BlockKey = $Blocks['Block']['key'];

		//$Nc2BbsPost = $this->getNc2Model('bbs_post.');
		//$nc2BbsPosts = $Nc2BbsPost->find('all');
		*/

		if ($nc2Circular['Nc2Circular']['reply_type'] == '0') {
			$nc3ReplyType = '1';
		} elseif ($nc2Circular['Nc2Circular']['reply_type'] == '1') {
			$nc3ReplyType = '3';
		} else {
			$nc3ReplyType = '2';
		}

		if (!$nc2Circular['Nc2Circular']['period']) {
			$nc3UseReplyDeadline = '0';
		} else {
			$nc3UseReplyDeadline = '1';
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];

		$data = [
			'CircularNoticeContent' => [
				'circular_notice_setting_key' => $nc3CircularNoticeSet['CircularNoticeSetting']['key'],
				'title_icon' => $this->_convertTitleIcon($nc2Circular['Nc2Circular']['icon_name']),
				//'language_id' => $nc3BlocksLanguages['BlocksLanguage']['language_id'],
				'is_active' => '1',
				'is_latest' => '1',
				'subject' => $nc2Circular['Nc2Circular']['circular_subject'],
				'content' => $this->_convertWYSIWYG($nc2Circular['Nc2Circular']['circular_body']),
				'reply_type' => $nc3ReplyType,
				//'is_room_target' =>
				'is_room_target' => '1',
				'public_type' => '1',
				'use_reply_deadline' => $nc3UseReplyDeadline,
				'reply_deadline' => $this->_convertDate($nc2Circular['Nc2Circular']['period']),
				'status' => '1',
				'created' => $this->_convertDate($nc2Circular['Nc2Circular']['insert_time']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Circular['Nc2Circular']),
				'publish_start' => $this->_convertDate($nc2Circular['Nc2Circular']['insert_time']),
				],
			'CircularNoticeChoices' => [
				],
			'Block' => [
				'id' => $nc3Blocks['Block']['id'],
			]
		];
		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Circular Array data of Nc2CircularBlock, Nc2Circular.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Circular) {
		if (isset($nc2Circular['Nc2CircularBlock'])) {
			return 'Nc2CircularBlock ' .
				'block_id:' . $nc2Circular['Nc2CircularBlock']['block_id'];
		}

		if (isset($nc2Circular['Nc2Circular'])) {
			return 'Nc2Circular ' .
				'circular_id:' . $nc2Circular['Nc2Circular']['circular_id'];
		}
	}
}