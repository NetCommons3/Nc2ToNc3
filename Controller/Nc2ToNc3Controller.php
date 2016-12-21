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
			if (!$this->Nc2ToNc3->setupNc2DataSource($config)) {
				$this->__setMessage($this->Nc2ToNc3->errors);
				return;
			}

			if (!$this->Nc2ToNc3->migration()) {
				$this->__setMessage($this->Nc2ToNc3->errors);
				return;
			}

			// TODOーsuccess画面へredirect
			return;
		}

		$this->request->data['Nc2ToNc3'] = $this->Nc2ToNc3->create();
	}

/**
 * Set message with FlashComponent
 *
 * @param string $message Message.
 * @return bool True on it access to config table of nc2.
 */
	private function __setMessage($message) {
		if (empty($message)) {
			return;
		}

		// 画面上部にalertをfadeさせる？
		//$this->NetCommons->setFlashNotification($message, ['interval' => NetCommonsComponent::ALERT_VALIDATE_ERROR_INTERVAL]);

		$options = [
			'key' => Nc2ToNc3::MESSAGE_KEY,
			'params' => ['class' => 'alert alert-danger']
		];
		$this->Flash->set($message, $options);
	}
}
