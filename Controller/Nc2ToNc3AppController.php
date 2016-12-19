<?php
/**
 * Nc2ToNc3AppController
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('AppController', 'Controller');

/**
 * Nc2ToNc3AppController
 *
 */
class Nc2ToNc3AppController extends AppController {

/**
 * 使用コンポーネント
 *
 * @var array
 */
	public $components = array(
		'ControlPanel.ControlPanelLayout',
		'M17n.SwitchLanguage',
		'NetCommons.Permission' => array(
			'type' => PermissionComponent::CHECK_TYEP_SYSTEM_PLUGIN,
			'allow' => array()
		),
		'Security',
		'SiteManager.SiteManager',
	);

/**
 * 使用ヘルパー
 *
 * @var array
 */
	public $helpers = array(
		'SystemManager.SystemManager',
	);
}
