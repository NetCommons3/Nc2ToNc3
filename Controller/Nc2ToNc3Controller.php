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

/**
 * Nc2ToNc3Controller
 *
 * @property Nc2ToNc3 $Nc2ToNc3
 */
class Nc2ToNc3Controller extends Nc2ToNc3AppController {

/**
 * use model
 *
 * @var array
 */
	public $uses = ['Nc2ToNc3.Nc2ToNc3'];

/**
 * Components
 *
 * @var array
 */
	public $components = [
		'Security',
		'ControlPanel.ControlPanelLayout',
		'NetCommons.Permission' => [
			'type' => PermissionComponent::CHECK_TYPE_SYSTEM_PLUGIN
		],
	];

/**
 * migration
 *
 * @return void
 */
	public function migration() {
		if ($this->request->is('post')) {
			$data = $this->request->data;
			if ($this->Nc2ToNc3->migration($data)) {
				// TODOーsuccess画面へredirect
				$this->redirect($this->referer());
				return;
			}

			$this->NetCommons->handleValidationError($this->Nc2ToNc3->validationErrors);
		} else {
			$this->request->data['Nc2ToNc3'] = $this->Nc2ToNc3->create();
		}
	}

}
