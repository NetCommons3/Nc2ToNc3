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
 * @param array $nc2Journal Array data of Nc2Journal, Nc2MultidatabaseContent.
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
			$colNo = $model->textColNo;
			$model->textColNo++;
		} else {
			$colNo = $model->varCharColNo;
			$model->varCharColNo++;
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
 * @param array $nc2MultidbContent Nc2MultidatabaseContent data.
 * @return array Nc3BlogEntry data.
 */

	public function generateNc3MultidbContent(Model $model, $nc2MultidbContent) {

		$nc2ContentId = $nc2MultidbContent['Nc2MultidatabaseContent']['content_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('MultidatabaseContent', $nc2ContentId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		// 対応するmetadataの取得も必要
		$nc3DbIds = $Nc2ToNc3Map->getMapIdList('Multidatabase', $nc2MultidbContent['Nc2MultidatabaseContent']['multidatabase_id']);
		if (!$nc3DbIds) {
			return [];
		}

		$nc3DbId = $nc3DbIds[$nc2MultidbContent['Nc2MultidatabaseContent']['multidatabase_id']];
		$Multidatabase = ClassRegistry::init('Multidatabases.Multidatabase');
		$multidatabase = $Multidatabase->findById($nc3DbId, ['block_id', 'key', ], null, -1);

		$Metadata = ClassRegistry::init('Multidatabases.MultidatabaseMetadata');
		$metadata =$Metadata->find('all', [
			'conditions' => [
				'multidatabase_id' => $nc3DbId,
			]
		]);
		$colNoList = Hash::combine($metadata, '{n}.MultidatabaseMetadata.id', '{n}.MultidatabaseMetadata.col_no');

		$metadata = Hash::combine($metadata, '{n}.MultidatabaseMetadata.id', '{n}.MultidatabaseMetadata');

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		// nc2metadata_content取得。
		$Nc2MetadataContent = $this->getNc2Model($model, 'multidatabase_metadata_content');
		$nc2metadataContents = $Nc2MetadataContent->find('all', [
			'conditions' => [
				'content_id' => $nc2ContentId,
			]
		]);

		// content mapping
		$dbKey = $multidatabase['Multidatabase']['key'];
		$blockId = $multidatabase['Multidatabase']['block_id'];

		// status agree_flag = 1未承認 0 承認 . temporary_flag 2 一時保存 0 公開
		// 'status' に入れる値の場合分け処理
		if ($nc2MultidbContent['Nc2MultidatabaseContent']['temporary_flag'] == '0' && $nc2MultidbContent['Nc2MultidatabaseContent']['agree_flag'] == '0') {
			$nc3Status = '1';
			$nc3IsActive = '1';
		} elseif ($nc2MultidbContent['Nc2MultidatabaseContent']['agree_flag'] == '1') {
			$nc3Status = '2';
			$nc3IsActive = '0';
		} elseif ($nc2MultidbContent['Nc2MultidatabaseContent']['temporary_flag'] != '0') {
			$nc3Status = '3';
			$nc3IsActive = '0';
		}

		$DbContent = ClassRegistry::init('Multidatabases.MultidatabaseContent');

		//
		$data = [
			'MultidatabaseContent'  => [
				'multidatabase_key' => $dbKey,
				'language_id' => $this->getLanguageIdFromNc2($model),
				'block_id' => $blockId,
				'status' => $nc3Status,
				'is_active' => $nc3IsActive,
				'is_latest' => 1,
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2MultidbContent['Nc2MultidatabaseContent']),
				'created' => $this->_convertDate($nc2MultidbContent['Nc2MultidatabaseContent']['insert_time']),
			],
			'Block' => [
				'id' => $blockId
			]
		];

		$Nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');

		$Nc2DbFile = $this->getNc2Model($model, 'multidatabase_file');

		// metadata content mapping
		foreach ($nc2metadataContents as $nc2metadataContent){
			$nc2MetadataId = $nc2metadataContent['Nc2MultidatabaseMetadataContent']['metadata_id'];
			$metadataMapIds = $Nc2ToNc3Map->getMapIdList('MultidatabaseMetadata', $nc2MetadataId);
			$nc3MetadataId = $metadataMapIds[$nc2MetadataId];

			$colNo = $colNoList[$nc3MetadataId];

			if (in_array($metadata[$nc3MetadataId]['type'], ['image', 'file'])){
				// ?action=multidatabase_action_main_filedownload&upload_id=1
				$value = $nc2metadataContent['Nc2MultidatabaseMetadataContent']['content'];
				$eualPos = strrpos($value, '=');
				if ($eualPos === false) {
					// アップロードされてない　→何もすることないか

				}else{
					// アップロードファイルあり
					$data['MultidatabaseContent']['value' . $colNo] = '';

					$nc2UploadId = substr($value, $eualPos + 1);
					$file = $Nc2ToNc3Upload->generateUploadFile($nc2UploadId);
					$fileFieldName = 'value'.$colNo.'_attach';
					//$fileFieldName = 'value'.$colNo.'';
					$data['MultidatabaseContent'][$fileFieldName] = $file;
					$DbContent->uploadSettings($fileFieldName);

					// ダウンロードパスワード
					$nc2DbFile = $Nc2DbFile->findByUploadId($nc2UploadId);
					if ($nc2DbFile['Nc2MultidatabaseFile']['file_password']) {
						$data['AuthorizationKey'][] = [
							'additional_id' => 'value' .$colNo,
							'authorization_key' => $nc2DbFile['Nc2MultidatabaseFile']['file_password']
						];

					}

					// download_content
					$data['DownloadCount']['value' . $colNo] = $nc2DbFile['Nc2MultidatabaseFile']['download_count'];


				}

			} else {
				$data['MultidatabaseContent']['value' . $colNo] = $nc2metadataContent['Nc2MultidatabaseMetadataContent']['content'];
			}

		}
		// TODO vote -> like

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
 * @param array $Nc2MultidbComment Nc2MultidatabaseContent data.
 * @return array Nc3ContentComment data.
 */
	public function generateNc3ContentCommentData(Model $model, $Nc2MultidbComment) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		$nc2ContentId = $Nc2MultidbComment['Nc2MultidatabaseComment']['content_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('MultidatabaseContent', $nc2ContentId);
		if (!$mapIdList) {
			// 親の記事の対応データ無し
			return [];
		}

		/* @var $MulitdbContent BlogEntry */
		$MulitdbContent = ClassRegistry::init('Multidatabases.MultidatabaseContent');
		$nc3MultidbContent = $MulitdbContent->findById($mapIdList[$nc2ContentId], ['key', 'block_id'], null, -1);
		if (!$nc3MultidbContent) {
			// 親の記事無し
			return [];
		}

		/* @var $BlogEntry BlogEntry */
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Block = $Block->findById($nc3MultidbContent['MultidatabaseContent']['block_id'], 'key', null, -1);
		if (!$nc3Block) {
			// ブロックデータ無し（あり得ない）
			return [];
		}
		$nc3BlockKey = $nc3Block['Block']['key'];

		/* @var $Nc2ToNc3Comment Nc2ToNc3ContentComment */
		$Nc2ToNc3Comment = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3ContentComment');
		$nc2CommentId = $Nc2MultidbComment['Nc2MultidatabaseComment']['comment_id'];
		$nc3ContentCommentId = $Nc2ToNc3Comment->getNc3ContentCommentId($nc3BlockKey, $nc2CommentId);
		if ($nc3ContentCommentId) {
			// 移行済み
			return [];
		}

		//'status' に入れる値の場合分け処理
		//if ($Nc2MultidbComment['Nc2MultidatabaseComment']['status'] == '0' && $Nc2MultidbComment['Nc2MultidatabaseComment']['agree_flag'] == '0') {
		//	$nc3Status = '1';
		//} elseif ($Nc2MultidbComment['Nc2MultidatabaseComment']['agree_flag'] == '1') {
		//	$nc3Status = '2';
		//}
		$nc3Status = WorkflowComponent::STATUS_PUBLISHED;

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['ContentComment'] = [
			'block_key' => $nc3BlockKey,
			'plugin_key' => 'multidatabases',
			'content_key' => $nc3MultidbContent['MultidatabaseContent']['key'],
			'status' => $nc3Status,
			'comment' => $Nc2MultidbComment['Nc2MultidatabaseComment']['comment_content'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($Nc2MultidbComment['Nc2MultidatabaseComment']),
			'created' => $this->_convertDate($Nc2MultidbComment['Nc2MultidatabaseComment']['insert_time']),
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

		if (isset($nc2Journal['Nc2MultidatabaseContent'])) {
			return 'Nc2MultidatabaseContent ' .
				'post_id:' . $nc2Journal['Nc2MultidatabaseContent']['post_id'];
		}
	}

}