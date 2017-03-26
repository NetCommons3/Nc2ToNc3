<?php
/**
 * Nc2ToNc3SearchBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

/**
 * Nc2ToNc3SearchBehavior
 *
 */
class Nc2ToNc3SearchBehavior extends Nc2ToNc3BaseBehavior {

	/* NC2ModuleName => NC3PluginName */
	private $__pluginNameMapping = [
		'announcement' => 'announcements',
		'bbs' => 'bbses',
		'cabinet' => 'cabinets',
		'calendar' => 'calendars',
		'circular' => 'circular_notices',
		'faq' => 'faqs',
		'journal' => 'blogs',
		'multidatabase' => 'multidatabases',
		'todo' => 'tasks',
	];

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2SearchBlock Array data of Nc2SearchBlock.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2SearchBlock) {
		return $this->__getLogArgument($nc2SearchBlock);
	}

/**
 * Generate Nc3SearchFrameSetting data.
 *
 * Data sample
 * data[Frame][id]:
 * data[SearchFrameSetting][id]:
 * data[SearchFrameSetting][frame_key]:
 * data[SearchFrameSetting][is_advanced]:0
 * data[SearchFrameSetting][created_user]:0
 * data[SearchFrameSetting][created]:0
 * data[SearchFramesPlugin][plugin_key]:0
 * data[SearchFramesPlugin][created_user]:0
 * data[SearchFramesPlugin][created]:0
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap Frame mapping data.
 * @param array $nc2SearchBlock Nc2SearchBlock data.
 * @return array Nc3SearchFrameSetting data.
 */
	public function generateNc3FrameSettingData(Model $model, $frameMap, $nc2SearchBlock) {

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Search', $nc2SearchBlock['Nc2SearchBlock']['block_id']);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$targetModules = explode(',', $nc2SearchBlock['Nc2SearchBlock']['default_target_module']);
		$pluginKeys = [];
		foreach ($targetModules as $targetModule) {
			if (isset($this->__pluginNameMapping[$targetModule])) {
				$pluginKeys[] = $this->__pluginNameMapping[$targetModule];
			}
		}
		if (empty($pluginKeys)) {
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
		];
		$data['SearchFrameSetting'] = [
			'id' => '',
			'frame_key' => $frameMap['Frame']['key'],
			'is_advanced' => $nc2SearchBlock['Nc2SearchBlock']['show_mode'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2SearchBlock['Nc2SearchBlock']),
			'created' => $this->_convertDate($nc2SearchBlock['Nc2SearchBlock']['insert_time']),
		];
		$data['SearchFramesPlugin'] = [
			'plugin_key' => $pluginKeys,
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2SearchBlock['Nc2SearchBlock']),
			'created' => $this->_convertDate($nc2SearchBlock['Nc2SearchBlock']['insert_time']),
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2SearchBlock Array data of Nc2SearchBlock.
 * @return string Log argument
 */
	private function __getLogArgument($nc2SearchBlock) {

		return 'Nc2SearchBlock ' .
			'block_id:' . $nc2SearchBlock['Nc2SearchBlock']['block_id'];
	}
}
