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
class Nc2ToNc3BlogBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2JournalBlock Array data
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2JournalBlock) {
		return $this->__getLogArgument($nc2JournalBlock);
	}

/**
 * Generate generateNc3Blog Data.
 *
 * Data sample
 * data[BlogFrameSetting][id]:
 * data[BlogFrameSetting][frame_key]:
 * data[BlogFrameSetting][room_id]:1
 * data[BlogFrameSetting][articles_per_page]:0
 * data[BlogFrameSetting][created]:
 * data[BlogFrameSetting][created-user]:
 * data[Blog][id]:
 * data[Blog][key]:
 * data[Blog][name]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2JournalBlock Nc2JournalBlock data.
 * @param array $nc2Journal Nc2Journal data.
 * @return array Nc3Blog data.
 */

	public function generateNc3BlogData(Model $model, $nc2JournalBlock, $nc2Journal) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2JournalBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		//$nc2JournalId = $nc2JournalBlock['Nc2JournalBlock']['journal_id'];

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Blog', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		/* @var $Nc2ToNc3User Nc2ToNc3User */
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

}
