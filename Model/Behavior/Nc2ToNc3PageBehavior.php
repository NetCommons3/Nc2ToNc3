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

}
