<?php
/**
 * Nc2ToNc3RoomBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3RoomBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3RoomBehavior
 *
 */
class Nc2ToNc3RoomBehavior extends Nc2ToNc3RoomBaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Page Nc2Page data.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Page) {
		return $this->__getLogArgument($nc2Page);
	}

/**
 * Get Nc2Room conditions.
 *
 * @param Model $model Model using this behavior.
 * @return array Nc2Room conditions.
 */
	public function getNc2RoomConditions(Model $model) {
		return $this->__getNc2RoomConditions();
	}

/**
 * Get other laguage Nc3Room id.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Page Nc2Page data.
 * @return array other laguage Nc3Room id.
 */
	public function getNc2OtherLaguageRoomIdList(Model $model, $nc2Page) {
		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->_getNc2Model('pages');
		$conditions = $this->__getNc2RoomConditions();
		$conditions += [
			'Nc2Page.lang_dirname !=' => $nc2Page['Nc2Page']['lang_dirname'],
			'Nc2Page.permalink' => $nc2Page['Nc2Page']['permalink'],
		];
		$query = [
			'fields' => [
				'Nc2Page.page_id',
				'Nc2Page.room_id'
			],
			'conditions' => $conditions,
			'recursive' => -1
		];
		$nc2OtherLaguageList = $Nc2Page->find('list', $query);

		return $nc2OtherLaguageList;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Page Nc2Page data
 * @return string Log argument
 */
	private function __getLogArgument($nc2Page) {
		return 'Nc2Page ' .
			'page_id:' . $nc2Page['Nc2Page']['page_id'] .
			'page_name:' . $nc2Page['Nc2Page']['page_name'];
	}

/**
 * Get Nc2Room conditions.
 *
 * @return array Nc2Room conditions.
 */
	private function __getNc2RoomConditions() {
		$conditions = [
			'Nc2Page.page_id = Nc2Page.room_id',
			'Nc2Page.private_flag' => '0',
			'Nc2Page.root_id !=' => '0',
		];

		return $conditions;
	}

}
