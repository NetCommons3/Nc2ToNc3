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
class Nc2ToNc3BlogBehavior extends Nc2ToNc3BaseBehavior
{

	/**
	 * Get Log argument.
	 *
	 * @param Model $model Model using this behavior.
	 * @param array $nc2Blog Array data of Nc2BlogManage, Nc2BlogBlock and Nc2BlogPlan.
	 * @return string Log argument
	 */
	public function getLogArgument(Model $model, $nc2JournalBlock)
	{
		return $this->__getLogArgument($nc2JournalBlock);
	}

	/**
	 * Generate Nc3BlogFrameSetting data.
	 *
	 * Data sample
	 * data[BlogFrameSetting][id]:
	 * data[BlogFrameSetting][frame_key]:
	 * data[BlogFrameSetting][room_id]:1
	 * data[BlogFrameSetting][is_myroom]:0
	 * data[BlogFrameSetting][display_type]:2
	 * data[BlogFrameSetting][is_select_room]:0
	 * data[BlogFrameSettingSelectRoom][1][room_id]:
	 * data[BlogFrameSettingSelectRoom][1][Blog_frame_setting_id]:1
	 * data[BlogFrameSettingSelectRoom][2][room_id]:
	 * data[BlogFrameSettingSelectRoom][2][Blog_frame_setting_id]:1
	 * data[BlogFrameSetting][start_pos]:0
	 * data[BlogFrameSetting][display_count]:3
	 * data[BlogFrameSetting][timeline_base_time]:8
	 *
	 * @param Model $model Model using this behavior.
	 * @param array $nc2BlogBlock Nc2BlogBlock data.
	 * @return array Nc3BlogFrameSetting data.
	 */
	public function generateNc3FrameSettingData(Model $model, $nc2JournalBlock)
	{
	 	/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2JournalBlock['Nc2JournalBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2JournalBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('BlogFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'frame_key' => $frameMap['Frame']['key'],
            'articles_per_page' =>  $nc2JournalBlock['Nc2JournalBlock']['visible_item'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2JournalBlock['Nc2JournalBlock']),
			'created' => $this->_convertDate($nc2JournalBlock['Nc2JournalBlock']['insert_time']),
		];

		/* @var $BlogFrameSetting BlogFrameSetting */
		$BlogFrameSetting = ClassRegistry::init('Blogs.BlogFrameSetting');
		$nc3BlogSetting = $BlogFrameSetting->findByFrameKey($frameMap['Frame']['key'], null, null, -1);

		if (!$nc3BlogSetting) {
			return $data;
		}

		$data['BlogFrameSetting'] = $data + $nc3BlogSetting['BlogFrameSetting'];
		return $data;
	}

	/**
	 * Get Log argument.
	 *
	 * @param array $nc2Blog Array data of Nc2BlogManage, nc2JournalBlock and Nc2BlogPlan.
	 * @return string Log argument
	 */
	private function __getLogArgument($nc2Blog)
	{
		if (isset($nc2Blog['Nc2BlogManage'])) {
			return 'Nc2BlogManage ' .
				'room_id:' . $nc2Block['Nc2BlogManage']['room_id'];
		}

		if (isset($nc2Blog['nc2JournalBlock'])) {
			return 'nc2JournalBlock ' .
				'block_id:' . $nc2Block['nc2JournalBlock']['block_id'];
		}

		return 'Nc2BlogPlan ' .
			'Blog_id:' . $nc2Block['Nc2BlogPlan']['Blog_id'] . ',' .
			'title:' . $nc2Block['Nc2BlogPlan']['title'];
	}

	/**
	 * Get map
	 *
	 * @param array|string $nc2BlogIds Nc2BlogPlan Blog_id.
	 * @return array Map data with Nc2Block block_id as key.
	 */

	//protected function _getMap($nc2BlogIds)
	//{
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Frame Frame */
	/*	$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$BlogEvent = ClassRegistry::init('Blogs.BlogEvent');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Frame', $nc2BlogIds);
		$query = [
			'fields' => [
				'BlogEvent.id',
			],
			'conditions' => [
				'BlogEvent.id' => $mapIdList
			],
			'recursive' => -1
		];
		$BlogEvents = $BlogEvent->find('all', $query);
		if (!$BlogEvents) {
			return $BlogEvents;
		}

		$map = [];
		foreach ($BlogEvents as $BlogEvent) {
			$nc2Id = array_search($BlogEvent['BlogEvent']['id'], $mapIdList);
			$map[$nc2Id] = $BlogEvent;
		}

		if (is_string($nc2BlogIds)) {
			$map = $map[$nc2BlogIds];
		}

		return $map;
	}
	*/
}
