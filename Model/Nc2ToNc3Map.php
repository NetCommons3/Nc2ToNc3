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
 * @param string $modelName Model name
 * @param array $data map data that nc2 id as key
 * @return bool True on success, false on validation errors
 * @throws InternalErrorException
 */
	public function saveMap($modelName, $data) {
		$this->begin();

		try {
			$nc2SiteId = $this->__getNc2SiteId();
			$nc2Id = array_keys($data)[0];

			$map = $this->findByNc2SiteIdAndModelNameAndNc2Id(
				$nc2SiteId,
				$modelName,
				$nc2Id,
				null,
				null,
				-1
			);

			if (!$map) {
				$map['Nc2ToNc3Map'] = [
					'nc2_site_id' => $nc2SiteId,
					'model_name' => $modelName,
					'nc2_id' => $nc2Id,
				];
				$map = $this->create($map);
			}

			$map['Nc2ToNc3Map']['map'] = serialize($data[$nc2Id]);

			$result = $this->save($map);
			if (!$result) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

		} catch (Exception $ex) {
			$this->rollback($ex);
		}

		$this->commit();

		return $result;
	}

/**
 * Get nc2 site_id.
 *
 * @return string nc2 site_id.
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
 * Get map
 *
 * @param string $modelName Model name
 * @param string $nc2Id Nc2 id.
 * @return array map.
 */
	public function getMap($modelName, $nc2Id = null) {
		$nc2SiteId = $this->__getNc2SiteId();

		$query = [
			'conditions' => [
				'nc2_site_id' => $nc2SiteId,
				'model_name' => $modelName,
			],
			'recursive' => -1
		];
		if (isset($nc2Id)) {
			$query['conditions']['nc2_id'] = $nc2Id;
		}

		$records = $this->find('all', $query);
		if (!$records) {
			return $records;
		}

		foreach ($records as $record) {
			$nc2IdAsKey = $record['Nc2ToNc3Map']['nc2_id'];
			$map[$nc2IdAsKey] = unserialize($record['Nc2ToNc3Map']['map']);
		}

		if (isset($nc2Id)) {
			$map = $map[$nc2Id];
		}

		return $map;
	}

}
