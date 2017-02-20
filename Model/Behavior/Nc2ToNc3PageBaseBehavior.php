<?php
/**
 * Nc2ToNc3PageBaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3PageBaseBehavior
 *
 */
class Nc2ToNc3PageBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get map
 *
 * @param array|string $nc2PageIds Nc2Page page_id.
 * @return array Map data with Nc2Page page_id as key.
 */
	protected function _getMap($nc2PageIds = null) {
		$map = [
			'Nc2のpage_id' => [
				'Page' => [
					'id' => 'Nc3のPage.id'
				]
			]
		];

		return $map;
	}

}
