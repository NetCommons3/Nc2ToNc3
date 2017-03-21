<?php
/**
 * Nc2ToNc3CircularNoticeBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3CircularNoticeBehavior
 *
 */
class Nc2ToNc3CircularNoticeBehavior extends Nc2ToNc3BaseBehavior
{
	/**
	 * Get Log argument.
	 *
	 * @param Model $model Model using this behavior.
	 * @param array $nc2CircularNotice Array data of Nc2CircularNoticeManage, Nc2CircularNoticeBlock and Nc2CircularNoticePlan.
	 * @return string Log argument
	 */
	public function getLogArgument(Model $model, $nc2CircularBlock)
	{
		return $this->__getLogArgument($nc2CircularBlock);
	}

	public function generateNc3CircularNoticeFrameSettingData(Model $model, $nc2CircularBlock)
	{
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
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
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CircularNotice', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			//return [];
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
/*			'BlocksLanguage' => [
				'language_id' => '',
				'name' => $nc2Journal['Nc2Journal']['journal_name']
			],
			'CircularNoticeSetting' => [
				'use_like' => $nc2Journal['Nc2Journal']['vote_flag'],
				'use_unlike' => '0',
				'use_comment' => $nc2Journal['Nc2Journal']['comment_flag'],
				'use_sns' => $nc2Journal['Nc2Journal']['sns_flag'],
			]
*/
		];
		return $data;
	}





	public function generateNc3CircularNoticeEntryData(Model $model, $nc2JournalPost)
	{
		$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CircularNoticeEntry', $nc2PostId);
		if ($mapIdList) {
			// 移行済み
			//return [];
		}

		$nc3CircularNoticeIds = $Nc2ToNc3Map->getMapIdList('CircularNotice', $nc2JournalPost['Nc2JournalPost']['journal_id']);
		$nc3CircularNoticeId = $nc3CircularNoticeIds[$nc2JournalPost['Nc2JournalPost']['journal_id']];


		$CircularNotice = ClassRegistry::init('CircularNotices.CircularNotice');
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3CircularNotice = $CircularNotice->findById($nc3CircularNoticeId, null, null, -1);
		$Blocks = $Block->findById($nc3CircularNotice['CircularNotice']['block_id'], null, null, -1);
		$nc3BlockKey = $Blocks['Block']['key'];
		//var_dump($nc3BlockKey);exit;

		//var_dump($nc3CircularNotice);exit;

		//$Nc2BbsPost = $this->getNc2Model('bbs_post.');
		//$nc2BbsPosts = $Nc2BbsPost->find('all');

		if ($nc2JournalPost['Nc2JournalPost']['status'] == '0' and $nc2JournalPost['Nc2JournalPost']['status'] == '0'){
			$nc3Status = '1';
		} elseif ($nc2JournalPost['Nc2JournalPost']['agree_flag'] == '1' ){
			$nc3Status = '2';
		} else {
			$nc3Status = '3';
		}
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		$data = [];
		$data = [
			'CircularNoticeEntry' => [
				'title' => $nc2JournalPost['Nc2JournalPost']['title'],
				'body1' => $nc2JournalPost['Nc2JournalPost']['content'],
				'body2' => $nc2JournalPost['Nc2JournalPost']['more_content'],
				'CircularNotice_key' => $nc3CircularNotice['CircularNotice']['key'],
				'status' => $nc3Status,
				'language_id' => $nc3CircularNotice['CircularNotice']['language_id'],
				'block_id' => $nc3CircularNotice['CircularNotice']['block_id'],
				'publish_start' => $this->_convertDate($nc2JournalPost['Nc2JournalPost']['journal_date']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalPost['Nc2JournalPost'])
				],
			'Block' => [
				'id' => $nc3CircularNotice['CircularNotice']['block_id'],
				'key' => $nc3BlockKey
			]
		];
//error_log(print_r('data no naka mimasu ', true)."\n\n", 3, LOGS."/debug.log");
//error_log(print_r($data, true)."\n\n", 3, LOGS."/debug.log");
//error_log(print_r($data['CircularNoticeEntry']['title'], true)."\n\n", 3, LOGS."/debug.log");
//error_log(print_r($data['CircularNoticeEntry']['publish_start'], true)."\n\n", 3, LOGS."/debug.log");
//		var_dump($data['CircularNoticeEntry']['publish_start']);exit;
		return $data;
	}
}