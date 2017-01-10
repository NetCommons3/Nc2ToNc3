<?php
/**
 * Nc2ToNc3UserAttributeNc2ItemDescriptionBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserAttributeNc2ItemDescriptionBehavior
 *
 */
class Nc2ToNc3UserAttributeNc2ItemDescriptionBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Nc2 item description.
 *
 * @var array
 */
	private $__nc2ItemDescriptions = null;

/**
 * Get Nc2 item description by id.
 *
 * @param Model $model Model using this behavior
 * @param string $itemId Nc2 item id
 * @return string Nc2 item description
 */
	public function getNc2ItemDescriptionById(Model $model, $itemId) {
		if (!isset($this->__nc2ItemDescriptions)) {
			$Nc2ItemsDesc = $this->_getNc2Model('items_desc');
			$query = [
				'fields' => [
					'Nc2ItemsDesc.item_id',
					'Nc2ItemsDesc.description'
				],
				'recursive' => -1
			];
			$this->__nc2ItemDescriptions = $Nc2ItemsDesc->find('list', $query);
		}

		return Hash::get($this->__nc2ItemDescriptions, [$itemId], '');
	}

}
