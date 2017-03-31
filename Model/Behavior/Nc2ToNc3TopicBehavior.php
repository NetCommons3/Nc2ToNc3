<?php
/**
 * Nc2ToNc3TopicBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3FaqBehavior
 *
 */
class Nc2ToNc3TopicBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2WhatsnewBlock Array data of nc2WhatsnewBlock.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2WhatsnewBlock) {
		return $this->__getLogArgument($nc2WhatsnewBlock);
	}

/**
 * Generate Nc3TopicFrameSetting data.
 *
 * Data sample
 * data[TopicFrameSetting][id]:
 * data[TopicFrameSetting][frame_key]:
 * data[TopicFrameSetting][content_per_page]:1
 * data[TopicFrameSetting][created_user]:
 * data[TopicFrameSetting][created]:
 * data[FaqSetting][use_workflow]:
 * data[FaqSetting][use_like]:
 * data[FaqSetting][use_unlike]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2WhatsnewBlock nc2WhatsnewBlock data.
 * @return array Nc3Faq data.
 */
	public function generateNc3TopicFrameSettingData(Model $model, $nc2WhatsnewBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2WhatsnewBlock['Nc2WhatsnewBlock']['block_id'];

		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2WhatsnewBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('TopicFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		//プラグインを配列化する
		$Nc2ToNc3Plugin = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Plugin');
		$nc2DisplayModules = explode(",", $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_modules']);
		$nc3PluginKeys = $Nc2ToNc3Plugin->getMap($nc2DisplayModules);

		$nc3FramePluginKey = [];
		foreach ($nc3PluginKeys as $nc3PluginKey) {
			$nc3FramePluginKey[] = $nc3PluginKey['Plugin']['key'];
		}

		$nc3ChoicesDisplayDay = array(1, 3, 7, 14, 30);
		$nc3ChoicesDisplayNum = array(1, 5, 10, 20, 50, 100);
		$nc2ValueDisplayDays = $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_days'];

		//'display_number'がNULLだとvalidateエラーになるためNULLの場合は、NC2のデフォルト値(5)を代入
		if (!$nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_number']) {
			$nc2ValueDisplayNum = '5';
		} else {
			$nc2ValueDisplayNum = $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_number'];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];
		$data = [
			'Frame' => [
				'id' => $frameMap['Frame']['id']
			],
			'Block' => [
				'id' => '',
				'key' => '',
				'room_id' => '',
				'plugin_key' => 'topics',
			],
			'BlocksLanguage' => [
				'language_id' => '',
			],
			'TopicFrameSetting' => [
				'id' => '',
				'frame_key' => $frameMap['Frame']['key'],
				'display_type' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_type'],
				'unit_type' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_flag'],
				'display_days' => $this ->_convertChoiceValue($nc2ValueDisplayDays, $nc3ChoicesDisplayDay),
				'display_number' => $this ->_convertChoiceValue($nc2ValueDisplayNum, $nc3ChoicesDisplayNum),
				'display_title' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_title'],
				'display_summary' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_description'],
				'display_room_name' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_room_name'],
				'display_category_name' => '0',
				'display_plugin_name' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_module_name'],
				'display_created_user' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_user_name'],
				'display_created' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['display_insert_time'],
				'use_rss_feed' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['allow_rss_feed'],
				'select_room' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['select_room'],
				'select_block' => '0',
				'select_plugin' => '1',
				'show_my_room' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['myroom_flag'], //前提条件
				'feed_summary' => $nc2WhatsnewBlock['Nc2WhatsnewBlock']['rss_description'], //前提条件
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2WhatsnewBlock['Nc2WhatsnewBlock']),
			],
			'TopicFramesPlugin' => [
				'frame_key' => $frameMap['Frame']['key'],
				'plugin_key' => $nc3FramePluginKey
			]
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2WhatsnewBlock Array data of nc2WhatsnewBlock.
 * @return string Log argument
 */
	private function __getLogArgument($nc2WhatsnewBlock) {
			return 'nc2WhatsnewBlock ' .
				'block_id:' . $nc2WhatsnewBlock['Nc2WhatsnewBlock']['block_id'];
	}
}
