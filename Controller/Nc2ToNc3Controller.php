<?php
/**
 * SystemManager Controller
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppController', 'Nc2ToNc3.Controller');
App::uses('Nc2ModelManager', 'Nc2ToNc3.Utility');

/**
 * Nc2ToNc3Controller
 *
 */
class Nc2ToNc3Controller extends Nc2ToNc3AppController {

/**
 * use model
 *
 * @var array
 */
	public $uses = [];

/**
 * Components
 *
 * @var array
 */
	public $components = [
		'ControlPanel.ControlPanelLayout',
		'NetCommons.Permission' => [
			'type' => PermissionComponent::CHECK_TYEP_SYSTEM_PLUGIN
		],
	];

/**
 * migration
 *
 * @return void
 */
	public function migration() {
		if ($this->request->is('post')) {
			$config = $this->request->data['Nc2ToNc3'];
			if (!Nc2ModelManager::migration($this, $config)) {
				return;
			}

		} else {
			$connectionObjects = ConnectionManager::enumConnectionObjects();
			$nc3config = $connectionObjects['master'];
			unset($nc3config['database'], $nc3config['prefix']);

			$this->request->data['Nc2ToNc3'] = $nc3config;

		}
	}
}
