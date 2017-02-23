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
App::uses('CakeRoute', 'Routing/Route');

/**
 * Nc2ToNc3PageBaseBehavior
 *
 */
class Nc2ToNc3PageBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * CakeRouter class to parseNc2Page permalink.
 *
 * @var array
 */
	private $__CakeRouter = null;

/**
 * Convert Nc2Page permalink.
 *
 * @param Model $model Model using this behavior.
 * @param strine $nc2Permalink Nc2Item data.
 * @return string Converted Nc2Page permalink.
 */
	public function convertPermalink(Model $model, $nc2Permalink) {
		return $this->_convertPermalink($nc2Permalink);
	}

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

/**
 * Convert Nc2Page permalink.
 *
 * @param strine $nc2Permalink Nc2Item data.
 * @return string Converted Nc2Page permalink.
 */
	protected function _convertPermalink($nc2Permalink) {
		// Pages.Routing/Route/SlugRoute の処理でRoutingされれなくなるため、変換しとく
		// @see https://github.com/NetCommons3/Pages/commit/b5740eb9923b50984760ce888d1d47501617f6d6#diff-7fdbf96769d3865d4e321fd323637187

		if (!$this->__CakeRouter) {
			$this->__CakeRouter = new CakeRoute('/*');
		}

		$url = '/' . $nc2Permalink;
		$route = $this->__CakeRouter->parse($url);

		// この処理で良いのか？
		$Space = ClassRegistry::init('Rooms.Space');
		$query = [
			'conditions' => [
				'permalink' => $route['pass'][0]
			],
			'recursive' => -1
		];
		if ($Space->find('count', $query)) {
			unset($route['pass'][0]);
		}

		return implode('/', $route['pass']);
	}

}
