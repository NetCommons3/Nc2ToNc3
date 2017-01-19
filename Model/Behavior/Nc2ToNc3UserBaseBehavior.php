<?php
/**
 * Nc2ToNc3UserBaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserBaseBehavior
 *
 */
class Nc2ToNc3UserBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * IdMap of nc2 and nc3.
 *
 * @var array
 */
	private $__idMap = null;

/**
 * Put id map.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2UserId Nc2User id.
 * @param string $nc3UserId Nc3User id.
 * @return void
 */
	public function putIdMap(Model $model, $nc2UserId, $nc3UserId) {
		$this->_putIdMap($nc2UserId, $nc3UserId);
	}

/**
 * Get id map.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2UserId Nc2User id.
 * @return array|string Id map.
 */
	public function getIdMap(Model $model, $nc2UserId = null) {
		return $this->_getIdMap($nc2UserId);
	}

/**
 * Put id map.
 *
 * @param string $nc2UserId Nc2User id.
 * @param string $nc3UserId Nc3User id.
 * @return void
 */
	protected function _putIdMap($nc2UserId, $nc3UserId) {
		$this->__idMap[$nc2UserId] = $nc3UserId;
	}

/**
 * Get id map
 *
 * @param string $nc2UserId Nc2User id.
 * @return array|string Id map.
 */
	protected function _getIdMap($nc2UserId = null) {
		if (isset($nc2UserId)) {
			return Hash::get($this->__idMap, [$nc2UserId]);
		}

		return $this->__idMap;
	}

}
