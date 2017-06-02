<?php
/**
 * Nc2ToNc3LinkBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3LinkBehavior
 *
 */
class Nc2ToNc3LinkBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Link Array data of Nc2Link.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Link) {
		return $this->__getLogArgument($nc2Link);
	}

/**
 * Generate Nc3LinkBlock data.
 *
 * Data sample
 * data[Frame][id]:
 * data[Block][id]:
 * data[Block][key]:
 * data[Block][room_id]:
 * data[Block][plugin_key]:links
 * data[Block][name]:
 * data[Block][public_type]:1
 * data[LinkBlock][key]:
 * data[LinkBlock][name]:
 * data[LinkBlock][created_user]:
 * data[LinkBlock][created]:
 * data[LinkSetting][use_workflow]:0
 * data[Categories]:
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap FrameMap data.
 * @param array $nc2Linklist Nc2Linklist data.
 * @return array Nc3Link data.
 */
	public function generateNc3LinkBlockData(Model $model, $frameMap, $nc2Linklist) {
		$nc2LinklistId = $nc2Linklist['Nc2Linklist']['linklist_id'];
		$linklistMap = $this->_getMap($nc2LinklistId);
		if ($linklistMap) {
			// 移行済みの場合
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
			'plugin_key' => 'links',
			'public_type' => 1,
		];
		$data['LinkBlock'] = [
			'key' => '',
			'name' => $nc2Linklist['Nc2Linklist']['linklist_name'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Linklist['Nc2Linklist']),
			'created' => $this->_convertDate($nc2Linklist['Nc2Linklist']['insert_time']),
		];
		$data['LinkSetting'] = [
			'use_workflow' => '0',
		];

		return $data;
	}

/**
 * Generate Nc3Link data.
 *
 * Data sample
 * data[Link][id]:
 * data[Link][key]:
 * data[Link][block_id]:
 * data[Link][status]:
 * data[Link][language_id]:
 * data[Link][category_id]:
 * data[Link][url]:
 * data[Link][title]:
 * data[Link][description]:
 * data[Link][click_count]:
 * data[LinkOrder][id]:
 * data[LinkOrder][block_key]:
 * data[LinkOrder][link_key]:
 * data[LinkOrder][category_key]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2LinklistLink Nc2LinklistLink data.
 * @return array Nc3Link data.
 */
	public function generateNc3LinkData(Model $model, $nc2LinklistLink) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2LinkId = $nc2LinklistLink['Nc2LinklistLink']['link_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Link', $nc2LinkId);
		if ($mapIdList) {
			return [];	// 移行済み
		}

		$nc2LinklistId = $nc2LinklistLink['Nc2LinklistLink']['linklist_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('LinkBlock', $nc2LinklistId);
		if (!$mapIdList) {
			return [];	// ブロックデータなし
		}
		$nc3BlockId = $mapIdList[$nc2LinklistId];

		/* @var $LinkBlock LinkBlock */
		$LinkBlock = ClassRegistry::init('Links.LinkBlock');
		$nc3LinkBlock = $LinkBlock->findById($nc3BlockId, null, null);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'Link' => [
				'id' => '',
				'key' => '',
				'block_id' => $nc3BlockId,
				'status' => '1',
				'language_id' => $nc3LinkBlock['LinkBlocksLanguage']['language_id'],
				'url' => $nc2LinklistLink['Nc2LinklistLink']['url'],
				'title' => $nc2LinklistLink['Nc2LinklistLink']['title'],
				'description' => $nc2LinklistLink['Nc2LinklistLink']['description'],
				'click_count' => $nc2LinklistLink['Nc2LinklistLink']['view_count'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2LinklistLink['Nc2LinklistLink']),
				'created' => $this->_convertDate($nc2LinklistLink['Nc2LinklistLink']['insert_time']),
				// 新着用に更新日を移行
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L146
				'modified' => $this->_convertDate($nc2LinklistLink['Nc2LinklistLink']['update_time']),
			],
			'LinkOrder' => [
				'id' => '',
				'block_key' => $nc3LinkBlock['LinkBlock']['key'],
				'link_key' => '',
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2LinklistLink['Nc2LinklistLink']),
				'created' => $this->_convertDate($nc2LinklistLink['Nc2LinklistLink']['insert_time']),
			],
		];

		return $data;
	}

/**
 * Generate Nc3LinkFrameSetting data.
 *
 * Data sample
 * data[LinkFrameSetting][id]:
 * data[LinkFrameSetting][frame_key]:
 * data[LinkFrameSetting][display_type]:
 * data[LinkFrameSetting][category_separator_line]:
 * data[LinkFrameSetting][list_style]:
 * data[LinkFrameSetting][open_new_tab]:
 * data[LinkFrameSetting][display_click_count]:
 * data[LinkFrameSetting][created_user]:
 * data[LinkFrameSetting][created]:
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap FrameMap data.
 * @param array $nc2LinklistBlock NC2LinklistBlock data.
 * @return array Nc3LinkFrameSetting data.
 */
	public function generateNc3LinkFrameSettingData(Model $model, $frameMap, $nc2LinklistBlock) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2BlockId = $nc2LinklistBlock['Nc2LinklistBlock']['block_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('LinkFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		// @see https://github.com/NetCommons3/Links/blob/3.1.2/Model/LinkFrameSetting.php#L119-L145
		$listStyle = $nc2LinklistBlock['Nc2LinklistBlock']['mark'];
		if ($listStyle === 'none' ||
			$listStyle === 'square'
		) {
			$listStyle = '';
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['LinkFrameSetting'] = [
			'id' => '',
			'frame_key' => $frameMap['Frame']['key'],
			'display_type' => $nc2LinklistBlock['Nc2LinklistBlock']['display'],
			'category_separator_line' => $nc2LinklistBlock['Nc2LinklistBlock']['line'],
			'list_style' => $listStyle,
			'open_new_tab' => $nc2LinklistBlock['Nc2LinklistBlock']['target_blank_flag'],
			'display_click_count' => $nc2LinklistBlock['Nc2LinklistBlock']['view_count_flag'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2LinklistBlock['Nc2LinklistBlock']),
			'created' => $this->_convertDate($nc2LinklistBlock['Nc2LinklistBlock']['insert_time']),
		];

		$nc2LinklistId = $nc2LinklistBlock['Nc2LinklistBlock']['linklist_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('LinkBlock', $nc2LinklistId);
		$nc3LinkBlockId = Hash::get($mapIdList, [$nc2LinklistId]);
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
			'plugin_key' => 'links',
			'block_id' => $nc3LinkBlockId,
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $Nc2Linklist Array data of Nc2Linklist, Nc2LinklistBlock and Nc2LinklistLink.
 * @return string Log argument
 */
	private function __getLogArgument($Nc2Linklist) {
		if (isset($Nc2Linklist['Nc2LinklistBlock'])) {
			return 'Nc2LinkBlock ' .
				'block_id:' . $Nc2Linklist['Nc2LinklistBlock']['block_id'];
		}

		if (isset($Nc2Linklist['Nc2Linklist'])) {
			return 'Nc2Linklist ' .
				'linklist_id:' . $Nc2Linklist['Nc2Linklist']['linklist_id'];
		}

		return 'Nc2LinklistLink ' .
			'link_id:' . $Nc2Linklist['Nc2LinklistLink']['link_id'];
	}

/**
 * Get map
 *
 * @param array|string $nc2LinklistIds Nc2CLinklist linklist_id.
 * @return array Map data with Nc2Block block_id as key.
 */
	protected function _getMap($nc2LinklistIds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $LinkBlock LinkBlock */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$LinkBlock = ClassRegistry::init('Links.LinkBlock');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('LinkBlock', $nc2LinklistIds);
		$query = [
			'fields' => [
				'LinkBlock.id',
				'LinkBlock.key',
			],
			'conditions' => [
				'LinkBlock.id' => $mapIdList,
			],
			'recursive' => -1,
			'callbacks' => false,
		];
		$nc3LinkBlocks = $LinkBlock->find('all', $query);
		if (!$nc3LinkBlocks) {
			return $nc3LinkBlocks;
		}

		$map = [];
		foreach ($nc3LinkBlocks as $nc3LinkBlock) {
			$nc2Id = array_search($nc3LinkBlock['LinkBlock']['id'], $mapIdList);
			$map[$nc2Id] = $nc3LinkBlock;
		}

		if (is_string($nc2LinklistIds)) {
			$map = $map[$nc2LinklistIds];
		}

		return $map;
	}
}
