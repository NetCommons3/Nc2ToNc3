<?php
/**
 * Nc2ToNc3BlogBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3BlogBehavior
 *
 */

class Nc2ToNc3BlogBehavior extends Nc2ToNc3BaseBehavior {
/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Journal Array data of Nc2Journal, Nc2JournalPost.
 * @return string Log argument
 */

	public function getLogArgument(Model $model, $nc2Journal) {
		return $this->__getLogArgument($nc2Journal);
	}

/**
 * Generate Nc3Blog data.
 *
 * Data sample
 * data[Frame][id]:26
 * data[Block][id]:
 * data[Block][key]:
 * data[BlocksLanguage][language_id]:
 * data[Block][room_id]:1
 * data[Block][plugin_key]:blocks
 * data[Blog][id]:
 * data[Blog][key]:
 * data[BlogSetting][use_workflow]:1
 * data[BlogSetting][use_comment_approval]:1
 * data[BlogFrameSetting][id]:
 * data[BlogFrameSetting][frame_key]:cdcd29729ec34e79b128d9e3d877b8ec
 * data[BlogFrameSetting][articles_per_page]:10
 * data[Blog][name]:ブログですよ
 * data[Block][public_type]:1
 * data[Block][publish_start]:
 * data[Block][publish_end]:
 * data[BlogSetting][use_comment]:0
 * data[BlogSetting][use_comment]:1
 * data[BlogSetting][use_like]:0
 * data[BlogSetting][use_like]:1
 * data[BlogSetting][use_unlike]:0
 * data[BlogSetting][use_unlike]:1
 * data[BlogSetting][use_sns]:0
 * data[BlogSetting][use_sns]:1
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Journal Nc2Journal data.
 * @param array $nc2JournalBlock Nc2JournalBlock data.
 * @return array Nc3Blog data.
 */
	public function generateNc3BlogData(Model $model, $nc2Journal, $nc2JournalCategory) {
		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$roomMap = $Nc2ToNc3Room->getMap($nc2Journal['Nc2Journal']['room_id']);
		if (!$roomMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2Journal));
			$this->_writeMigrationLog($message);
			return [];
		}

		// NC2に配置されていないと登録できない。とりあえずRoom.room_idから取得できる先頭のFrame.idを使用しとく
		// @see https://github.com/NetCommons3/NetCommons3/issues/811
		/* @var $Frame Frame */
		$Frame = ClassRegistry::init('Frames.Frame');
		$nc3RoomId = $roomMap['Room']['id'];
		$nc3Frame = $Frame->findByRoomIdAndPluginKey($nc3RoomId, 'blogs', 'id', null, -1);
		if (!$nc3Frame) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2Journal));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$nc2JournalId = $nc2Journal['Nc2Journal']['journal_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Blog', $nc2JournalId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'Frame' => [
				'id' => $nc3Frame['Frame']['id']
			],
			'Block' => [
				'id' => '',
				'room_id' => $nc3RoomId,
				'plugin_key' => 'blogs',
				'name' => $nc2Journal['Nc2Journal']['journal_name'],
				'public_type' => $nc2Journal['Nc2Journal']['active_flag']
			],
			'Blog' => [
				'id' => '',
				'key' => '',
				'name' => $nc2Journal['Nc2Journal']['journal_name'],
				'created' => $this->_convertDate($nc2Journal['Nc2Journal']['insert_time']),
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
			],
			'Category' => [
				'name' => $nc2JournalCategory['Nc2JournalCategory']['category_name']
			]
		];

		return $data;
	}

/**
 * Generate Nc3BlogFameSettingData data.
 *
 * Data sample
 * data[BlogFrameSetting][id]:
 * data[BlogFrameSetting][frame_key]:
 * data[BlogFrameSetting][articles_per_page]:10
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2JournalBlock Nc2JournalBlock data.
 * @return array Nc3BlogFameSetting data.
 */
	public function generateNc3BlogFrameSettingData(Model $model, $nc2JournalBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2JournalBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BlogFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			return [];	// 移行済み
		}

		$nc2JournalId = $nc2JournalBlock['Nc2JournalBlock']['journal_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Blog', $nc2JournalId);

		/* @var $Blog Blog */
		$Blog = ClassRegistry::init('Blogs.Blog');
		$nc3BlogId = Hash::get($mapIdList, [$nc2JournalId]);
		$nc3Blog = $Blog->findById($nc3BlogId, 'block_id', null, -1);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['BlogFrameSetting'] = [
			'frame_key' => $frameMap['Frame']['key'],
			'articles_per_page' => $nc2JournalBlock['Nc2JournalBlock']['visible_item'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalBlock['Nc2JournalBlock']),
			'created' => $this->_convertDate($nc2JournalBlock['Nc2JournalBlock']['insert_time']),
		];
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
			'plugin_key' => 'blogs',
			'block_id' => Hash::get($nc3Blog, ['Blog', 'block_id']),
		];

		return $data;
	}

/**
 * Generate Nc3BlogEntry data.
 *
 * Data sample
 * data[BlogEntry][key]:
 * data[Frame][id]:15
 * data[Block][id]:3
 * data[BlogEntry][title_icon]:
 * data[BlogEntry][title]:aaaa
 * data[BlogEntry][body1]:<p>aaaaaa</p>
 * data[BlogEntry][body2]:
 * data[BlogEntry][publish_start]:2017-03-15 23:14:32
 * data[WorkflowComment][comment]:
 * data[Block][key]:9873556528b4ac6eaa22e52e28633c94
 * data[BlogEntry][status]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2JournalPost Nc2JournalPost data.
 * @return array Nc3BlogEntry data.
 */

	public function generateNc3BlogEntryData(Model $model, $nc2JournalPost) {
		$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BlogEntry', $nc2PostId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$nc3BlogIds = $Nc2ToNc3Map->getMapIdList('Blog', $nc2JournalPost['Nc2JournalPost']['journal_id']);
		if (!$nc3BlogIds) {
			return [];
		}
		$nc3BlogId = $nc3BlogIds[$nc2JournalPost['Nc2JournalPost']['journal_id']];

		$Blog = ClassRegistry::init('Blogs.Blog');
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Blog = $Blog->findById($nc3BlogId, null, null, -1);
		$Blocks = $Block->findById($nc3Blog['Blog']['block_id'], null, null, -1);
		$nc3BlockKey = $Blocks['Block']['key'];

		//'status' に入れる値の場合分け処理
		if ($nc2JournalPost['Nc2JournalPost']['status'] == '0' && $nc2JournalPost['Nc2JournalPost']['agree_flag'] == '0') {
			$nc3Status = '1';
			$nc3IsActive = '1';
		} elseif ($nc2JournalPost['Nc2JournalPost']['agree_flag'] == '1') {
			$nc3Status = '2';
			$nc3IsActive = '0';
		} elseif ($nc2JournalPost['Nc2JournalPost']['status'] != '0') {
			$nc3Status = '3';
			$nc3IsActive = '0';
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		$data = [];
		$data = [
			'BlogEntry' => [
				'title' => $nc2JournalPost['Nc2JournalPost']['title'],
				'body1' => $this->_convertWYSIWYG($nc2JournalPost['Nc2JournalPost']['content']),
				'body2' => $this->_convertWYSIWYG($nc2JournalPost['Nc2JournalPost']['more_content']),
				'blog_key' => $nc3Blog['Blog']['key'],
				'status' => $nc3Status,
				'is_active' => $nc3IsActive,
				'language_id' => $nc3Blog['Blog']['language_id'],
				'block_id' => $nc3Blog['Blog']['block_id'],
				'publish_start' => $this->_convertDate($nc2JournalPost['Nc2JournalPost']['journal_date']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalPost['Nc2JournalPost']),
				'title_icon' => $this->_convertTitleIcon($nc2JournalPost['Nc2JournalPost']['icon_name'])
				],
			'Block' => [
				'id' => $nc3Blog['Blog']['block_id'],
				'key' => $nc3BlockKey
			]
		];
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

		if (isset($nc2Journal['Nc2JournalPost'])) {
			return 'Nc2JournalPost ' .
				'post_id:' . $nc2Journal['Nc2JournalPost']['post_id'];
		}
	}
}