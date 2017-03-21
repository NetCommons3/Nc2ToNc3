<?php
/**
 * Nc2ToNc3BlogBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3BlogBehavior
 *
 */
class Nc2ToNc3BlogBehavior extends Nc2ToNc3BaseBehavior
{
	/**
	 * Get Log argument.
	 *
	 * @param Model $model Model using this behavior.
	 * @param array $nc2Blog Array data of Nc2BlogManage, Nc2BlogBlock and Nc2BlogPlan.
	 * @return string Log argument
	 */
	public function getLogArgument(Model $model, $nc2Journal)
	{
		return $this->__getLogArgument($nc2Journal);
	}

	public function generateNc3BlogData(Model $model, $nc2Journal, $nc2JournalBlock)
	{
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2JournalBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$nc2JournalId = $nc2Journal['Nc2Journal']['journal_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Blog', $nc2JournalId);
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
				'plugin_key' => 'blogs',
				'name' => $nc2Journal['Nc2Journal']['journal_name'],
				'public_type' => $nc2Journal['Nc2Journal']['active_flag']
			],
			'Blog' => [
				'id' => '',
				'key' => '',
				'name' => $nc2Journal['Nc2Journal']['journal_name'],
				'created' => $this->_convertDate($nc2JournalBlock['Nc2JournalBlock']['insert_time']),
			],
			'BlogFrameSetting' => [
				'id' => '',
				'frame_key' => $frameMap['Frame']['key'],
				'articles_per_page' => $nc2JournalBlock['Nc2JournalBlock']['visible_item'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalBlock['Nc2JournalBlock']),
				'created' => $this->_convertDate($nc2JournalBlock['Nc2JournalBlock']['insert_time']),
			],
			'BlocksLanguage' => [
				'language_id' => '',
				'name' => $nc2Journal['Nc2Journal']['journal_name']
			],
			'BlogSetting' => [
				'use_like' => $nc2Journal['Nc2Journal']['vote_flag'],
				'use_unlike' => '0',
				'use_comment' => $nc2Journal['Nc2Journal']['comment_flag'],
				'use_sns' => $nc2Journal['Nc2Journal']['sns_flag'],
			]
		];
		return $data;
	}





	public function generateNc3BlogEntryData(Model $model, $nc2JournalPost)
	{
		$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BlogEntry', $nc2PostId);
		if ($mapIdList) {
			// 移行済み
			//return [];
		}

		$nc3BlogIds = $Nc2ToNc3Map->getMapIdList('Blog', $nc2JournalPost['Nc2JournalPost']['journal_id']);
		$nc3BlogId = $nc3BlogIds[$nc2JournalPost['Nc2JournalPost']['journal_id']];


		$Blog = ClassRegistry::init('Blogs.Blog');
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Blog = $Blog->findById($nc3BlogId, null, null, -1);
		$Blocks = $Block->findById($nc3Blog['Blog']['block_id'], null, null, -1);
		$nc3BlockKey = $Blocks['Block']['key'];
		//var_dump($nc3BlockKey);exit;

		//var_dump($nc3Blog);exit;

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
			'BlogEntry' => [
				'title' => $nc2JournalPost['Nc2JournalPost']['title'],
				'body1' => $nc2JournalPost['Nc2JournalPost']['content'],
				'body2' => $nc2JournalPost['Nc2JournalPost']['more_content'],
				'blog_key' => $nc3Blog['Blog']['key'],
				'status' => $nc3Status,
				'language_id' => $nc3Blog['Blog']['language_id'],
				'block_id' => $nc3Blog['Blog']['block_id'],
				'publish_start' => $this->_convertDate($nc2JournalPost['Nc2JournalPost']['journal_date']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalPost['Nc2JournalPost'])
				],
			'Block' => [
				'id' => $nc3Blog['Blog']['block_id'],
				'key' => $nc3BlockKey
			]
		];
//error_log(print_r('data no naka mimasu ', true)."\n\n", 3, LOGS."/debug.log");
//error_log(print_r($data, true)."\n\n", 3, LOGS."/debug.log");
//error_log(print_r($data['BlogEntry']['title'], true)."\n\n", 3, LOGS."/debug.log");
//error_log(print_r($data['BlogEntry']['publish_start'], true)."\n\n", 3, LOGS."/debug.log");
//		var_dump($data['BlogEntry']['publish_start']);exit;
		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Journal Array data of Nc2CalendarManage, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Journal) {
		if (isset($nc2Journal['Nc2Journal'])) {
			return 'Nc2Journal ' .
				'journal_id:' . $nc2Journal['Nc2Journal']['journal_id'];
		}

		if (isset($nc2Journal['Nc2JournalBlock'])) {
			return 'Nc2JournalBlock ' .
				'block_id:' . $nc2Journal['Nc2JournalBlock']['block_id'];
		}

//		return 'Nc2CalendarPlan ' .
//			'calendar_id:' . $nc2Calendar['Nc2CalendarPlan']['calendar_id'] . ',' .
//			'title:' . $nc2Calendar['Nc2CalendarPlan']['title'];
	}
}