<?php
/**
 * Nc2ToNc3CalendarBaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('CakeRoute', 'Routing/Route');
App::uses('PageContainer', 'Pages.Model');

/**
 * Nc2ToNc3CalendarBaseBehavior
 *
 */
class Nc2ToNc3CalendarBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get map
 *
 * @param Model $model Model using this behavior.
 * @param array|string $nc2ModuleIds Nc2Module module_id.
 * @return array Id map.
 */
	public function getCalendarFrameSettingMap(Model $model, $nc2BlockIds = null) {
		return $this->_getCalendarFrameSettingMap($nc2BlockIds);
	}

/**
 * Get map
 *
 * @param array|string $nc2BlockIds Nc2Block block_id.
 * @return array Id map.
 */
	protected function _getCalendarFrameSettingMap($nc2BlockIds = null) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $CalendarFrameSetting CalendarFrameSetting */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$CalendarFrameSetting = ClassRegistry::init('Calendars.CalendarFrameSetting');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('CalendarFrameSetting', $nc2BlockIds);
		$query = [
			'fields' => [
				'CalendarFrameSetting.id',
			],
			'conditions' => [
				'CalendarFrameSetting.id' => $mapIdList
			],
			'recursive' => -1
		];
		$calendarSettings = $CalendarFrameSetting->find('all', $query);
		if (!$calendarSettings) {
			return $calendarSettings;
		}

		$map = [];
		foreach ($calendarSettings as $calendarSetting) {
			$nc2Id = array_search($calendarSetting['CalendarFrameSetting']['id'], $mapIdList);
			$map[$nc2Id] = $calendarSetting;
		}

		if (is_string($nc2BlockIds)) {
			$map = $map[$nc2BlockIds];
		}

		return $map;
	}

}
