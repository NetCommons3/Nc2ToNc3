<?php
/**
 * Nc2ToNc3RssReaderBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Xml', 'Utility');

/**
 * Nc2ToNc3RssReaderBehavior
 *
 */
class Nc2ToNc3RssReaderBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Iframe Array data of Nc2Iframe.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Iframe) {
		return $this->__getLogArgument($nc2Iframe);
	}

/**
 * Generate Nc3RssReader data.
 *
 * Data sample
 * data[Frame][id]:
 * data[Block][id]:
 * data[Block][key]:
 * data[Block][room_id]:
 * data[Block][plugin_key]:
 * data[Block][name]:
 * data[Block][public_type]:
 * data[RssReader][id]:0
 * data[RssReader][key]:0
 * data[RssReader][url]:0
 * data[RssReader][title]:0
 * data[RssReader][link]:0
 * data[RssReader][summary]:0
 * data[RssReader][status]:0
 * data[RssReader][created_user]:0
 * data[RssReader][created]:0
 * data[RssReaderSetting][use_workflow]:0
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap Frame mapping data.
 * @param array $nc2RssBlock Nc2RssBlock data.
 * @return array Nc3RssReader data.
 */
	public function generateNc3RssReaderData(Model $model, $frameMap, $nc2RssBlock) {

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('RssReader', $nc2RssBlock['Nc2RssBlock']['block_id']);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$rss = Xml::build($nc2RssBlock['Nc2RssBlock']['url']);
		if ($rss->getName() === 'feed') {
			$name = (string)$rss->title;
			$link = (string)$rss->link->attributes()->href;
			$summary = (string)$rss->subtitle;
		} else {
			$name = (string)$rss->channel->title;
			$link = (string)$rss->channel->link;
			$summary = (string)$rss->channel->description;
		}
		if (!$name) {
			$name = $nc2RssBlock['Nc2RssBlock']['site_name'];
		}
		if (!$name) {
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
        $data['Frame'] = [
            'id' => $frameMap['Frame']['id'],
        ];
        $data['Block'] = [
            'id' => '',
            'key' => '',
            'room_id' => $frameMap['Frame']['room_id'],
            'plugin_key' => 'rss_readers',
            'name' => $name,
            'public_type' => '1',
        ];
		$data['RssReader'] = [
			'id' => '',
			'key' => '',
			'url' => $nc2RssBlock['Nc2RssBlock']['url'],
			'title' => $name,
			'link' => $link,
			'summary' => $summary,
			'status' => '1',
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2RssBlock['Nc2RssBlock']),
			'created' => $this->_convertDate($nc2RssBlock['Nc2RssBlock']['insert_time']),
		];
		$data['RssReaderSetting'] = [
			'use_workflow' => '0',
		];

		return $data;
	}

/**
 * Generate Nc3RssReaderFrameSetting data.
 *
 * Data sample
 * data[RssReaderFrameSetting][id]:
 * data[RssReaderFrameSetting][frame_key]:
 * data[RssReaderFrameSetting][display_number_per_page]:
 * data[RssReaderFrameSetting][created_user]:0
 * data[RssReaderFrameSetting][created]:0
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap Frame mapping data.
 * @param array $nc2RssBlock Nc2RssBlock data.
 * @return array Nc3RssReaderFrameSetting data.
 */
	public function generateNc3FrameSettingData(Model $model, $frameMap, $nc2RssBlock) {

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['RssReaderFrameSetting'] = [
			'id' => '',
			'frame_key' => $frameMap['Frame']['key'],
			'display_number_per_page' => $nc2RssBlock['Nc2RssBlock']['visible_row'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2RssBlock['Nc2RssBlock']),
			'created' => $this->_convertDate($nc2RssBlock['Nc2RssBlock']['insert_time']),
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2RssBlock Array data of Nc2RssBlock.
 * @return string Log argument
 */
	private function __getLogArgument($nc2RssBlock) {

		return 'Nc2RssBlock ' .
			'block_id:' . $nc2RssBlock['Nc2RssBlock']['block_id'];
	}
}
