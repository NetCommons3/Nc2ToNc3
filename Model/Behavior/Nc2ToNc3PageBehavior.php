<?php
/**
 * Nc2ToNc3PageBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3PageBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3PageBehavior
 *
 */
class Nc2ToNc3PageBehavior extends Nc2ToNc3PageBaseBehavior {

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
 * Save existing map
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Pages Nc2Page data
 * @return void
 */
	public function saveExistingMap(Model $model, $nc2Pages) {
		// permalinkが同一データをmapデータに登録しようと考えたが、room_id、language_idも絡むし、
		// Nc2ToNc3PageBaseBehavior::_convertPermalinkもしないとなので、1レコードづつの確認になる。
		// 処理が重くなるので保留
		//   →Nc3に既存の同名permalinkのデータは移行されない。

		// [Nc2Page.permalink => Nc2Page.page_id]]
		//$idList = Hash::combine($nc2Pages, '{n}.Nc2Page.permalink', '{n}.Nc2Page.page_id');

		/* @var $User User */
		/*
		$User = ClassRegistry::init('Pages.Page');
		$query = [
			'fields' => [
				'Page.id',
				'Page.permalink',
			],
			'conditions' => [
				'Page.permalink' => array_keys($idList),
				'Page.room_id' => array_keys($idList)
			],
			'recursive' => -1
		];
		*/
	}

/**
 * Get Nc2Page conditions.
 *
 * @param Model $model Model using this behavior.
 * @return array Nc2Page conditions.
 */
	public function getNc2PageConditions(Model $model) {
		return $this->__getNc2PageConditions();
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
 * Get Nc2Page conditions.
 *
 * @return array Nc2Page conditions.
 */
	private function __getNc2PageConditions() {
		$conditions = [
			'Nc2Page.page_id != Nc2Page.room_id',
			'Nc2Page.parent_id !=' => '0',
		];

		return $conditions;
	}

}
