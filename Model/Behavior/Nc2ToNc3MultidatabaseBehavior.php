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

class Nc2ToNc3MultidatabaseBehavior extends Nc2ToNc3BaseBehavior {
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
 * @param array $nc2Multidatabase Nc2Journal data.
 * @return array Nc3Blog data.
 */
	public function generateNc3MultidatabaseData(Model $model, $nc2Multidatabase) {
		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$roomMap = $Nc2ToNc3Room->getMap($nc2Multidatabase['Nc2Multidatabase']['room_id']);
		if (!$roomMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2Multidatabase));
			$this->_writeMigrationLog($message);
			return [];
		}

		// NC2に配置されていないと登録できない。とりあえずRoom.room_idから取得できる先頭のFrame.idを使用しとく
		// @see https://github.com/NetCommons3/NetCommons3/issues/811
		/* @var $Frame Frame */
		$Frame = ClassRegistry::init('Frames.Frame');
		$nc3RoomId = $roomMap['Room']['id'];
		$nc3Frame = $Frame->findByRoomIdAndPluginKey($nc3RoomId, 'multidatabases', ['id','key'], null, -1);
		if (!$nc3Frame) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2Multidatabase));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2MultidatabaseId = $nc2Multidatabase['Nc2Multidatabase']['multidatabase_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Multidatabase', $Nc2MultidatabaseId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc3CreatedUser = $Nc2ToNc3User->getCreatedUser($nc2Multidatabase['Nc2Multidatabase']);
		$nc3Created = $this->_convertDate($nc2Multidatabase['Nc2Multidatabase']['insert_time']);

		//$Nc2MultidatabaseBlock = $model->getNc2Model($model, 'multidatabase_block');
		//$nc2MultidatabaseBlock = $Nc2MultidatabaseBlock->find('first', [
		//	'conditions' => [
		//
		//	]
		//])

		$data = [
			'Frame' => [
				'id' => $nc3Frame['Frame']['id']
			],
			'Block' => [
				'id' => '',
				'room_id' => $nc3RoomId,
				'plugin_key' => 'blogs',
				'name' => $nc2Multidatabase['Nc2Multidatabase']['multidatabase_name'],
				'public_type' => $nc2Multidatabase['Nc2Multidatabase']['active_flag'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
			'Multidatabase' => [
				'id' => '',
				'key' => '',
				'name' => $nc2Multidatabase['Nc2Multidatabase']['multidatabase_name'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
			'BlocksLanguage' => [
				'language_id' => '',
				'name' => $nc2Multidatabase['Nc2Multidatabase']['multidatabase_name'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
			'MultidatabaseSetting' => [
				'use_like' => $nc2Multidatabase['Nc2Multidatabase']['vote_flag'],
				'use_unlike' => '0',
				'use_comment' => $nc2Multidatabase['Nc2Multidatabase']['comment_flag'],
				//'use_sns' => $nc2Multidatabase['Nc2Multidatabase']['sns_flag'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
			// 無いとsave失敗するので仮にデータ入れる
			'MultidatabaseFrameSetting' => [
				//'frame_key' => $nc3Frame['Frame']['key'],
				//'content_per_page' => 10,
				//'default_sort_type' => 0,
				//'created_user' => $nc3CreatedUser,
				//'created' => $nc3Created,
			],
			'MultidatabaseMetadata' => [
				0 => [
					[
						'name' => 'ダミータイトル',
						'col_no' => 1,
						'type' => 'text',
					]
				],
				1 => [],
				2 => [],
				3 => [],
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
 * @param array $nc2MultidatabaseBlock Nc2MultidatabaseBlock data.
 * @return array Nc3BlogFameSetting data.
 */
	public function generateNc3MultidatabaseFrameSettingData(Model $model, $nc2MultidatabaseBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2MultidatabaseBlock['Nc2MultidatabaseBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2MultidatabaseBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('MultidatabaseFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			return [];	// 移行済み
		}

		$nc2MultidatabaseId = $nc2MultidatabaseBlock['Nc2MultidatabaseBlock']['multidatabase_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Multidatabase', $nc2MultidatabaseId);

		/* @var $Multidatabase Blog */
		$Multidatabase = ClassRegistry::init('Multidatabases.Multidatabase');
		$nc3MultidatabaseId = Hash::get($mapIdList, [$nc2MultidatabaseId]);
		$nc3Multidatabase = $Multidatabase->findById($nc3MultidatabaseId, 'block_id', null, -1);
		if (!$nc3Multidatabase) {
			return [];	// Nc3Blogデータなし
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		switch($nc2MultidatabaseBlock['Nc2MultidatabaseBlock']['default_sort']){
			case 'seq': //カスタマイズ順
				$sortType = '0'; // 指定無し　にマッピングしておく
				break;
			case 'date': // 新着順
				$sortType = 'created_desc'; // 作成日順
				break;
			case 'date_asc': //入力順
				$sortType = 'created';
				break;
			case 'vote' : // 投票順　
				$sortType = '0'; // 指定無しにマッピングしておく
				break;
			case '1': // タイトル順
				$sortType = 'value1';
				break;
		}

		$data['MultidatabaseFrameSetting'] = [
			'frame_key' => $frameMap['Frame']['key'],
			'content_per_page' => $nc2MultidatabaseBlock['Nc2MultidatabaseBlock']['visible_item'],
			'default_sort_type' => $sortType,

			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2MultidatabaseBlock['Nc2MultidatabaseBlock']),
			'created' => $this->_convertDate($nc2MultidatabaseBlock['Nc2MultidatabaseBlock']['insert_time']),
		];
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
			'plugin_key' => 'multidatabases',
			'block_id' => Hash::get($nc3Multidatabase, ['Multidatabase', 'block_id']),
		];

		return $data;
	}

	public function generateNc3MultidatabaseMetadata(Model $model, $nc2Metadatum) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('MultidatabaseMetadata', $nc2Metadatum['Nc2MultidatabaseMetadata']['metadata_id']);
		if ($mapIdList) {
			return [];	// 移行済み
		}

		$nc2MultidatabaseId = $nc2Metadatum['Nc2MultidatabaseMetadata']['multidatabase_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Multidatabase', $nc2MultidatabaseId);

		$Nc2Multidatabase = $this->getNc2Model($model, 'multidatabase');
		$nc2Multidatabase = $Nc2Multidatabase->findByMultidatabaseId($nc2MultidatabaseId);

		/* @var $Multidatabase Blog */
		$Multidatabase = ClassRegistry::init('Multidatabases.Multidatabase');
		$nc3MultidatabaseId = Hash::get($mapIdList, [$nc2MultidatabaseId]);
		$nc3Multidatabase = $Multidatabase->findById($nc3MultidatabaseId, ['block_id'], null, -1);
		if (!$nc3Multidatabase) {
			return [];	// nc3Multidatabaseがない
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		// type 数値から文字列へ
		// key:NC2 value:NC3
		$metadataTypeMap = [
			1 => 'text',
			2 => 'textarea',
			3 => 'link',
			4 => 'select',
			12 => 'checkbox',
			6 => 'wysiwyg',
			5 => 'file',
			0 => 'image',
			7 => 'autonumber',
			8 => 'mail',
			9 => 'date',
			10 => 'created',
			11 => 'updated'
  		];

		$type = $metadataTypeMap[$nc2Metadatum['Nc2MultidatabaseMetadata']['type']];

		if (in_array($type, ['select', 'checkbox'])) {
			$selectContent = explode('|', $nc2Metadatum['Nc2MultidatabaseMetadata']['select_content']);
			$selections = json_encode($selectContent);
		} else {
			$selections = '';
		}
		// col_no _contentでの保存先カラム番号。自動でやってる？ text、WYSIWYG型は80以降。Findして＋1する。なければ80

		/* @see MultidatabaseMetadataEdit::addColNo(); */
		if (in_array($type, ['textarea', 'wysiwyg', 'select', 'checkbox'])) {
			$colNo = $model->varCharColNo;
			$model->varCharColNo++;

		} else {
			$colNo = $model->textColNo;
			$model->textColNo++;
		}

		// nc2multidatabase.title_metadata_idで指定されてるIDがタイトル
		$isTitle = ($nc2Metadatum['Nc2MultidatabaseMetadata']['metadata_id'] ==
			$nc2Multidatabase['Nc2Multidatabase']['title_metadata_id']);


		$data['MultidatabaseMetadata'] = [
			//'key' => $nc3Multidatabase['Multidatabase']['key'], //
			'multidatabase_id' => $nc3MultidatabaseId,
			'language_id' => $this->getLanguageIdFromNc2($model),
			'name' => $nc2Metadatum['Nc2MultidatabaseMetadata']['name'],
			'col_no' => $colNo,
			'type' => $type,
			'rank' => $nc2Metadatum['Nc2MultidatabaseMetadata']['display_sequence'] -1,
			'position' => $nc2Metadatum['Nc2MultidatabaseMetadata']['display_pos'] -1,
			'selections' => $selections,
			'is_require' => $nc2Metadatum['Nc2MultidatabaseMetadata']['require_flag'],
			'is_title' => $isTitle,
			'is_searchable' => $nc2Metadatum['Nc2MultidatabaseMetadata']['search_flag'],
			'is_sortable' => $nc2Metadatum['Nc2MultidatabaseMetadata']['sort_flag'],
			'is_file_dl_require_auth' => $nc2Metadatum['Nc2MultidatabaseMetadata']['file_password_flag'],
			'is_visible_file_dl_counter' => $nc2Metadatum['Nc2MultidatabaseMetadata']['file_count_flag'],
			'is_visible_field_name' => $nc2Metadatum['Nc2MultidatabaseMetadata']['name_flag'],
			'is_visible_list' => $nc2Metadatum['Nc2MultidatabaseMetadata']['list_flag'],
			'is_visible_detail' => $nc2Metadatum['Nc2MultidatabaseMetadata']['detail_flag'],
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
		$data = [
			'BlogEntry' => [
				'blog_key' => $nc3Blog['Blog']['key'],
				'status' => $nc3Status,
				'is_active' => $nc3IsActive,
				'language_id' => $nc3Blog['Blog']['language_id'],
				'title' => $nc2JournalPost['Nc2JournalPost']['title'],
				'body1' => $model->convertWYSIWYG($nc2JournalPost['Nc2JournalPost']['content']),
				'body2' => $model->convertWYSIWYG($nc2JournalPost['Nc2JournalPost']['more_content']),
				'publish_start' => $this->_convertDate($nc2JournalPost['Nc2JournalPost']['journal_date']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalPost['Nc2JournalPost']),
				'created' => $this->_convertDate($nc2JournalPost['Nc2JournalPost']['insert_time']),
				'block_id' => $nc3Blog['Blog']['block_id'],
				'title_icon' => $this->_convertTitleIcon($nc2JournalPost['Nc2JournalPost']['icon_name']),
			],
			'Block' => [
				'id' => $nc3Blog['Blog']['block_id'],
				'key' => $nc3BlockKey
			]
		];
		return $data;
	}

/**
 * Generate Nc3ContentComment data.
 *
 * Data sample
 * data[ContentComment][plugin_key]:blogs
 * data[ContentComment][content_key]:aaa
 * data[ContentComment][status]:1
 * data[ContentComment][comment]:コメント００１
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2JournalPost Nc2JournalPost data.
 * @return array Nc3ContentComment data.
 */
	public function generateNc3ContentCommentData(Model $model, $nc2JournalPost) {
		if (!$nc2JournalPost['Nc2JournalPost']['content']) {
			// トラックバック送信データはNc2JournalPost.contentが空
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		$nc2ParentId = $nc2JournalPost['Nc2JournalPost']['parent_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BlogEntry', $nc2ParentId);
		if (!$mapIdList) {
			// 親の記事の対応データ無し
			return [];
		}

		/* @var $BlogEntry BlogEntry */
		$BlogEntry = ClassRegistry::init('Blogs.BlogEntry');
		$nc3BlogEntry = $BlogEntry->findById($mapIdList[$nc2ParentId], ['key', 'block_id'], null, -1);
		if (!$nc3BlogEntry) {
			// 親の記事無し
			return [];
		}

		/* @var $BlogEntry BlogEntry */
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Block = $Block->findById($nc3BlogEntry['BlogEntry']['block_id'], 'key', null, -1);
		if (!$nc3Block) {
			// ブロックデータ無し（あり得ない）
			return [];
		}
		$nc3BlockKey = $nc3Block['Block']['key'];

		/* @var $Nc2ToNc3Comment Nc2ToNc3ContentComment */
		$Nc2ToNc3Comment = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3ContentComment');
		$nc2PostId = $nc2JournalPost['Nc2JournalPost']['post_id'];
		$nc3ContentCommentId = $Nc2ToNc3Comment->getNc3ContentCommentId($nc3BlockKey, $nc2PostId);
		if ($nc3ContentCommentId) {
			// 移行済み
			return [];
		}

		//'status' に入れる値の場合分け処理
		if ($nc2JournalPost['Nc2JournalPost']['status'] == '0' && $nc2JournalPost['Nc2JournalPost']['agree_flag'] == '0') {
			$nc3Status = '1';
		} elseif ($nc2JournalPost['Nc2JournalPost']['agree_flag'] == '1') {
			$nc3Status = '2';
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['ContentComment'] = [
			'block_key' => $nc3BlockKey,
			'plugin_key' => 'blogs',
			'content_key' => $nc3BlogEntry['BlogEntry']['key'],
			'status' => $nc3Status,
			'comment' => $nc2JournalPost['Nc2JournalPost']['content'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalPost['Nc2JournalPost']),
			'created' => $this->_convertDate($nc2JournalPost['Nc2JournalPost']['insert_time']),
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

		if (isset($nc2Journal['Nc2MultidatabaseBlock'])) {
			return 'Nc2MultidatabaseBlock ' .
				'block_id:' . $nc2Journal['Nc2MultidatabaseBlock']['block_id'];
		}

		if (isset($nc2Journal['Nc2JournalPost'])) {
			return 'Nc2JournalPost ' .
				'post_id:' . $nc2Journal['Nc2JournalPost']['post_id'];
		}
	}

}