<?php
/**
 * Nc2ToNc3Map
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('AppModel', 'Model');

/**
 * Nc2ToNc3Map
 *
 */
class Nc2ToNc3Map extends AppModel {

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Base'];

/**
 * Nc2 site_id.
 *
 * @var array
 */
	private $__nc2SiteId = null;

/**
 * Save map
 *
 * @param array $data Nc2ToNc3Map data.
 * @return bool True on success, false on validation errors.
 * @throws InternalErrorException
 */
	public function saveMap($data) {
		$this->begin();

		try {
			$nc2SiteId = $this->__getNc2SiteId();
			$data['Nc2ToNc3Map']['nc2_site_id'] = $nc2SiteId;

			$map = $this->findByNc2SiteIdAndModelNameAndNc2Id(
				$nc2SiteId,
				$data['Nc2ToNc3Map']['model_name'],
				$data['Nc2ToNc3Map']['nc2_id'],
				null,
				null,
				-1
			);
			if (!$map) {
				$data = $this->create($data);
			}
			if ($map) {
				$data['Nc2ToNc3Map']['id'] = $map['Nc2ToNc3Map']['id'];
			}

			$data = $this->save($data);
			if (!$data) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

		} catch (Exception $ex) {
			$this->rollback($ex);
		}

		$this->commit();

		return $data;
	}

/**
 * Get nc2 site_id.
 *
 * @return string Nc2 site_id.
 */
	private function __getNc2SiteId() {
		if (isset($this->__nc2SiteId)) {
			return $this->__nc2SiteId;
		}

		/* @var $Nc2Site AppModel */
		$Nc2Site = $this->getNc2Model('sites');
		$data = $Nc2Site->findBySelfFlag('1', 'site_id', null, -1);
		$this->__nc2SiteId = $data['Nc2Site']['site_id'];

		return $this->__nc2SiteId;
	}

/**
 * Get id list
 *
 * @param string $modelName Model name
 * @param array|string $nc2Ids Nc2 id.
 * @return array Id list.
 */
	public function getMapIdList($modelName, $nc2Ids = null) {
		$nc2SiteId = $this->__getNc2SiteId();

		$query = [
			'fields' => [
				'nc2_id',
				'nc3_id',
			],
			'conditions' => [
				'nc2_site_id' => $nc2SiteId,
				'model_name' => $modelName,
			],
			'recursive' => -1
		];
		if (isset($nc2Ids)) {
			$query['conditions']['nc2_id'] = $nc2Ids;
		}
		$idList = $this->find('list', $query);

		return $idList;
	}

}
