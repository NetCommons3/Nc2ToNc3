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
 * @return array Nc3Bbs data.
 */

	public function generateNc3BbsData(Model $model, $nc2Bbs, $nc2BbsBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2BbsBlock['Nc2BbsBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2BbsBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$nc2BbsId = $nc2Bbs['Nc2Bb']['bbs_id'];
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Bbs', $nc2BbsId);
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
				'plugin_key' => 'bbses',
				'name' => $nc2Bbs['Nc2Bb']['bbs_name'],
				'public_type' => $nc2Bbs['Nc2Bb']['activity']
			],
			'Bbs' => [
				'id' => '',
				'key' => '',
				'name' => $nc2Bbs['Nc2Bb']['bbs_name'],
				'created' => $this->_convertDate($nc2BbsBlock['Nc2BbsBlock']['insert_time']),
			],
			'BbsFrameSetting' => [
				'id' => '',
				'frame_key' => $frameMap['Frame']['key'],
				'articles_per_page' => $nc2BbsBlock['Nc2BbsBlock']['visible_row'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2BbsBlock['Nc2BbsBlock']),
				'created' => $this->_convertDate($nc2BbsBlock['Nc2BbsBlock']['insert_time']),
			],
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

		if (!$nc2BbsPost['Nc2BbsPost']['parent_id']) {
			$nc3BbsParentId = '';
			$nc3BbsRootId = '';
			$nc3BbsArticleNo = '1';
		} else {
			$mapIdList = [];
			//NC2 parent_idをgetMapに引き渡し、NC3 parent_idを取得する
			$mapIdList = $Nc2ToNc3Map->getMapIdList('BbsArticle', $nc2BbsPost['Nc2BbsPost']['parent_id']);
			$nc3BbsParentId = $mapIdList[$nc2BbsPost['Nc2BbsPost']['parent_id']];
			//NC2 topic_idをgetMapに引き渡し、NC3 root_idを取得する
			$mapIdList = $Nc2ToNc3Map->getMapIdList('BbsArticle', $nc2BbsPost['Nc2BbsPost']['topic_id']);
			$nc3BbsRootId = $mapIdList[$nc2BbsPost['Nc2BbsPost']['topic_id']];

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
				'content' => $this->_convertWYSIWYG($nc2BbsPostBody['Nc2BbsPostBody']['body']),
				'status' => $nc3Status,
				'is_active' => $nc3IsActive,
				'is_latest' => '1',
				'language_id' => $nc3Bbs['Bbs']['language_id'],
				'block_id' => $nc3Bbs['Bbs']['block_id'],
				//'publish_start' => $this->_convertDate($nc2BbsPost['Nc2BbsPost']['bbs_date']),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2BbsPost['Nc2BbsPost']),
				'title_icon' => $this->_convertTitleIcon($nc2BbsPost['Nc2BbsPost']['icon_name'])
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

		if (isset($nc2Bbs['Nc2BbsPost'])) {
			return 'Nc2BbsPost ' .
				'post_id:' . $nc2Bbs['Nc2BbsPost']['post_id'];
		}
	}
}