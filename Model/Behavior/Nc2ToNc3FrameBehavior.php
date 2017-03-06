<?php
/**
 * Nc2ToNc3FrameBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3FrameBehavior
 *
 */
class Nc2ToNc3FrameBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Block Nc2Block data.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Block) {
		return $this->__getLogArgument($nc2Block);
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Block Nc2Block data.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Block) {
		return 'Nc2Block ' .
			'block_id:' . $nc2Block['Nc2Block']['block_id'] . ',' .
			'block_name:' . $nc2Block['Nc2Block']['block_name'];
	}

/**
 * Get map
 *
 * @param array|string $nc2BlockIds Nc2Block block_id.
 * @return array Map data with Nc2Block block_id as key.
 */
	protected function _getMap($nc2BlockIds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Frame Frame */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Frame = ClassRegistry::init('Frames.Frame');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Frame', $nc2BlockIds);
		$query = [
			'fields' => [
				'Frame.id',
				'Frame.room_id',
			],
			'conditions' => [
				'Frame.id' => $mapIdList
			],
			'recursive' => -1
		];
		$frames = $Frame->find('all', $query);
		if (!$frames) {
			return $frames;
		}

		$map = [];
		foreach ($frames as $frame) {
			$nc2Id = array_search($frame['Frame']['id'], $mapIdList);
			$map[$nc2Id] = $frame;
		}

		if (is_string($nc2BlockIds)) {
			$map = $map[$nc2BlockIds];
		}

		return $map;
	}

}
