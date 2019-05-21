<?php
/**
 * Nc2ToNc3BbsBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('BbsFrameSetting', 'Bbses.Model');

/**
 * Nc2ToNc3BbsBehavior
 *
 */

class Nc2ToNc3BbsBehavior extends Nc2ToNc3BaseBehavior {
/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Bbs Array data of Nc2Bbs, Nc2BbsPost.
 * @return string Log argument
 */

	public function getLogArgument(Model $model, $nc2Bbs) {
		return $this->__getLogArgument($nc2Bbs);
	}

/**
 * Generate Nc3Bbs data.
 *
 * Data sample
 * data[Frame][id]:25
 * data[Block][id]:
 * data[Block][key]:
 * data[BlocksLanguage][language_id]:
 * data[Block][room_id]:1
 * data[Block][plugin_key]:blocks
 * data[Frame][id]:25
 * data[Block][id]:
 * data[Block][key]:
 * data[BlocksLanguage][language_id]:
 * data[Block][room_id]:1
 * data[Block][plugin_key]:blocks
 * data[Bbs][id]:
 * data[Bbs][key]:
 * data[BbsSetting][use_workflow]:1
 * data[BbsSetting][use_comment_approval]:1
 * data[BbsFrameSetting][id]:
 * data[BbsFrameSetting][frame_key]:bf730fc65c5af34cd6624f733a38b5cc
 * data[BbsFrameSetting][articles_per_page]:10
 * data[Bbs][name]:NC3掲示板のテスト
 * data[Block][public_type]:1
 * data[Block][publish_start]:
 * data[Block][publish_end]:
 * data[BbsSetting][use_comment]:0
 * data[BbsSetting][use_comment]:1
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Bbs Nc2Bbs data.
 * @param array $nc2BbsBlock Nc2BbsBlock data.
 * @param int $nc3RoomId nc3 room id.
 * @return array Nc3Bbs data.
 */

	public function generateNc3BbsData(Model $model, $nc2Bbs, $nc2BbsBlock, $nc3RoomId) {
		$frameMap = [];
		if ($nc2BbsBlock) {
			/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
			$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
			$nc2BlockId = $nc2BbsBlock['Nc2BbsBlock']['block_id'];
			$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
			//if (!$frameMap) {
			//	$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2BbsBlock));
			//	$this->_writeMigrationLog($message);
			//	return [];
			//}
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$nc2BbsId = $nc2Bbs['Nc2Bb']['bbs_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Bbs', $nc2BbsId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		//$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			//'Frame' => [
			//	'id' => $frameMap['Frame']['id']
			//],
			'Block' => [
				'id' => '',
				//'room_id' => $frameMap['Frame']['room_id'],
				'room_id' => $nc3RoomId,
				'plugin_key' => 'bbses',
				'name' => $nc2Bbs['Nc2Bb']['bbs_name'],
				'public_type' => $nc2Bbs['Nc2Bb']['activity']
			],
			'Bbs' => [
				'id' => '',
				'key' => '',
				'name' => $nc2Bbs['Nc2Bb']['bbs_name'],
				//'created' => $this->_convertDate($nc2BbsBlock['Nc2BbsBlock']['insert_time']),
				'created' => $this->_convertDate($nc2Bbs['Nc2Bb']['insert_time']),
			],
			//'BbsFrameSetting' => [
			//	'id' => '',
			//	'frame_key' => $frameMap['Frame']['key'],
			//	'articles_per_page' => $nc2BbsBlock['Nc2BbsBlock']['visible_row'],
			//	'created_user' => $Nc2ToNc3User->getCreatedUser($nc2BbsBlock['Nc2BbsBlock']),
			//	'created' => $this->_convertDate($nc2BbsBlock['Nc2BbsBlock']['insert_time']),
			//],
			'BlocksLanguage' => [
				'language_id' => '',
				'name' => $nc2Bbs['Nc2Bb']['bbs_name']
			],
			'BbsSetting' => [
				'use_like' => $nc2Bbs['Nc2Bb']['vote_flag'],
				'use_unlike' => '0',
				'use_comment' => $nc2Bbs['Nc2Bb']['child_flag'],
			//	'use_comment_approval' => $nc2Bbs['Nc2Bb']['sns_flag'],
			//	'use_workflow' => $nc2Bbs['Nc2Bb']['sns_flag'],
			]
		];
		if ($frameMap) {
			$data['Frame'] = [
				'id' => $frameMap['Frame']['id'],
			];
		}

		return $data;
	}

/**
 * Generate Nc3BbsArticle data.
 *
 * Data sample
 *
 * data[Frame][id]:15
 * data[Frame][block_id]:3
 * data[Block][id]:3
 * data[Block][key]:6cf0447107cf3fdf545d69c4ca0d7cbe
 * data[Bbs][id]:1
 * data[Bbs][key]:b696aed9b257594f73f2171f2d8d2ee4
 * data[Bbs][name]:NC3
 * data[BbsArticle][id]:
 * data[BbsArticle][key]:
 * data[BbsArticle][language_id]:2
 * data[BbsArticle][bbs_key]:b696aed9b257594f73f2171f2d8d2ee4
 * data[BbsArticle][block_id]:3
 * data[BbsArticleTree][id]:
 * data[BbsArticleTree][bbs_key]:b696aed9b257594f73f2171f2d8d2ee4
 * data[BbsArticleTree][bbs_article_key]:
 * data[BbsArticleTree][root_id]:
 * data[BbsArticleTree][parent_id]:
 * data[BbsArticle][title_icon]:
 * data[BbsArticle][title]:テスト　掲示板
 * data[BbsArticle][content]:<p>テスト</p>
 * data[WorkflowComment][comment]:
 * data[Block][key]:6cf0447107cf3fdf545d69c4ca0d7cbe
 * data[BbsArticle][status_]:0
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2BbsPost Nc2BbsPost data.
 * @param array $nc2BbsPostBody Nc2BbsPostBody data.
 * @return array Nc3BbsArticle data.
 */

	public function generateNc3BbsArticleData(Model $model, $nc2BbsPost, $nc2BbsPostBody) {
		$nc2PostId = $nc2BbsPost['Nc2BbsPost']['post_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = [];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BbsArticle', $nc2PostId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$nc3BbsIds = $Nc2ToNc3Map->getMapIdList('Bbs', $nc2BbsPost['Nc2BbsPost']['bbs_id']);
		if (!$nc3BbsIds) {
			return [];
		}
		$nc3BbsId = $nc3BbsIds[$nc2BbsPost['Nc2BbsPost']['bbs_id']];

		$Bbs = ClassRegistry::init('Bbses.Bbs');
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Bbs = $Bbs->findById($nc3BbsId, null, null, -1);
		$nc3Blocks = $Block->findById($nc3Bbs['Bbs']['block_id'], null, null, -1);
		//$nc3BlockKey = $nc3Blocks['Block']['key'];

		if ($nc2BbsPost['Nc2BbsPost']['status'] == '0') {
			$nc3Status = '1';
			$nc3IsActive = '1';
		} else {
			$nc3Status = '3';
			$nc3IsActive = '0';
		}

		$nc2ParentId = $nc2BbsPost['Nc2BbsPost']['parent_id'];
		if (!$nc2ParentId) {
			$nc3BbsParentId = '';
			$nc3BbsRootId = '';
			$nc3BbsArticleNo = '1';
		} else {
			// PHPMD.ExcessiveMethodLength に引っかかるので別メソッド化
			$nc3ParentArticle = $this->__getNc3ParentArticle($nc2ParentId);
			$nc3BbsParentId = $nc3ParentArticle['BbsArticleTree']['id'];
			$nc3BbsRootId = $nc3ParentArticle['BbsArticleTree']['root_id'];
			if (!$nc3BbsRootId) {
				$nc3BbsRootId = $nc3BbsParentId;
			}

			//現時点でのBbsArticleTreeでのroot_idごとのarticle_no最大値を取得して、1インクリメントする。
			//ただし、root_idのみのときは、root_idがnullのもの(article_noは1)しかなく取得できないため、'2'とする
			$BbsArticleTree = ClassRegistry::init('Bbses.BbsArticleTree');
			$BbsArticleTrees = $BbsArticleTree->findByRootId($nc3BbsRootId, array("fields" => "MAX(article_no) as max_article_no"));
			if (!$BbsArticleTrees[0]['max_article_no']) {
				$nc3BbsArticleNo = '2';
			} else {
				$nc3BbsArticleNo = $BbsArticleTrees[0]['max_article_no'] + 1;
			}
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');

		$data = [];
		$data = [
			'BbsArticle' => [
				'bbs_id' => $nc3BbsId,
				'bbs_key' => $nc3Bbs['Bbs']['key'],
				'title' => $nc2BbsPost['Nc2BbsPost']['subject'],
				'content' => $model->convertWYSIWYG($nc2BbsPostBody['Nc2BbsPostBody']['body']),
				'status' => $nc3Status,
				'is_active' => $nc3IsActive,
				'is_latest' => '1',
				'language_id' => $nc3Bbs['Bbs']['language_id'],
				'block_id' => $nc3Bbs['Bbs']['block_id'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2BbsPost['Nc2BbsPost']),
				'created' => $this->_convertDate($nc2BbsPost['Nc2BbsPost']['insert_time']),
				'title_icon' => $this->_convertTitleIcon($nc2BbsPost['Nc2BbsPost']['icon_name']),
				// 新着用に更新日を移行
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L146
				'modified' => $this->_convertDate($nc2BbsPost['Nc2BbsPost']['update_time']),
			],
			'Bbs' => [
				'id' => $nc3BbsId,
				'key' => $nc3Bbs['Bbs']['key'],
				'name' => $nc3Bbs['Bbs']['name']
			],
			'Block' => [
				'id' => $nc3Bbs['Bbs']['block_id'],
				'key' => $nc3Blocks['Block']['key']
			],
			'BbsArticleTree' => [
				'id' => '',
				'bbs_key' => $nc3Bbs['Bbs']['key'],
				'bbs_article_key' => '',
				'root_id' => $nc3BbsRootId,
				'parent_id' => $nc3BbsParentId,
				'article_no' => $nc3BbsArticleNo
			],
		];
		if ($nc2BbsPost['Nc2BbsPost']['vote_num']) {
			$data['Like'] = [
				'plugin_key' => 'bbses',
				'block_key' => $nc3Blocks['Block']['key'],
				'like_count' => $nc2BbsPost['Nc2BbsPost']['vote_num'],
			];
		}

		return $data;
	}

/**
 * Generate Nc3BbsFameSettingData data.
 *
 * Data sample
 * data[BbsFrameSetting][id]:
 * data[BbsFrameSetting][frame_key]:
 * data[BbsFrameSetting][articles_per_page]:10
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2BbsBlock Nc2BbsBlock data.
 * @return array Nc3BbsFameSetting data.
 */
	public function generateNc3BbsFrameSettingData(Model $model, $nc2BbsBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2BbsBlock['Nc2BbsBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2BbsBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BbsFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			return [];	// 移行済み
		}

		$nc2BbsId = $nc2BbsBlock['Nc2BbsBlock']['bbs_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Bbs', $nc2BbsId);

		/* @var $Bbs Bbs */
		$Bbs = ClassRegistry::init('Bbses.Bbs');
		$nc3BbsId = Hash::get($mapIdList, [$nc2BbsId]);
		$nc3Bbs = $Bbs->findById($nc3BbsId, 'block_id', null, -1);
		if (!$nc3Bbs) {
			return [];	// Nc3Bbsデータなし
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['BbsFrameSetting'] = [
			'frame_key' => $frameMap['Frame']['key'],
			'articles_per_page' => $nc2BbsBlock['Nc2BbsBlock']['visible_row'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2BbsBlock['Nc2BbsBlock']),
			'created' => $this->_convertDate($nc2BbsBlock['Nc2BbsBlock']['insert_time']),
		];
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
			'plugin_key' => 'bbses',
			'block_id' => Hash::get($nc3Bbs, ['Bbs', 'block_id']),
		];

		//表示タイプ(NC3.1.4以降対応)
		if ($nc2BbsBlock['Nc2BbsBlock']['expand'] === '1') {
			//フラット表示
			$data['BbsFrameSetting']['display_type'] = BbsFrameSetting::DISPLAY_TYPE_FLAT;
		} else {
			if ($nc2BbsBlock['Nc2BbsBlock']['display'] === '0') {
				//根記事一覧
				$data['BbsFrameSetting']['display_type'] = BbsFrameSetting::DISPLAY_TYPE_ROOT;
			} else {
				//NC2の最新記事一覧、全件一覧および過去記事は、NC3では全件一覧にする
				$data['BbsFrameSetting']['display_type'] = BbsFrameSetting::DISPLAY_TYPE_ALL;
			}
		}
		if ($nc2BbsBlock['Nc2BbsBlock']['display'] === '1') {
			//最新記事一覧の場合、表示件数を1にする
			$data['BbsFrameSetting']['articles_per_page'] = 1;
		}

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Bbs Array data of Nc2Bbs, Nc2BbsPost.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Bbs) {
		if (isset($nc2Bbs['Nc2Bb'])) {
			return 'Nc2Bbs ' .
				'Bbs_id:' . $nc2Bbs['Nc2Bb']['bbs_id'];
		}

		if (isset($nc2Bbs['Nc2BbsBlock'])) {
			return 'Nc2BbsPost ' .
				'block_id:' . $nc2Bbs['Nc2BbsBlock']['block_id'];
		}

		return 'Nc2BbsPost ' .
			'post_id:' . $nc2Bbs['Nc2BbsPost']['post_id'];
	}

/**
 * Get Nc3ParentArticle.
 *
 * @param string $nc2ParentId Nc2BbsPost parent_id.
 * @return string Log argument
 */
	private function __getNc3ParentArticle($nc2ParentId) {
		//NC2 parent_idをgetMapに引き渡し、NC3 parent_idを取得する
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $BbsArticle BbsArticle */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$BbsArticle = ClassRegistry::init('Bbses.BbsArticle');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('BbsArticle', $nc2ParentId);
		// BbsArticleTreeとLEFT JOINしているので取得可能
		// @see https://github.com/NetCommons3/Bbses/blob/3.1.0/Model/BbsArticle.php#L127-L144
		$nc3ParentArticle = $BbsArticle->findById(
			$mapIdList[$nc2ParentId],
			[
				'BbsArticleTree.id',
				'BbsArticleTree.root_id',
			],
			null,
			null,
			-1
		);

		return $nc3ParentArticle;
	}

}
