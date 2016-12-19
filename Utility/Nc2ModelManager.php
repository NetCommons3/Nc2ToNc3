<?php
/**
 * Nc2ModelManager
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('ConnectionManager', 'Model');

/**
 * Nc2ModelManager
 *
 */
class Nc2ModelManager {

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const CONNECTION_NAME = 'nc2';

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const VALID_VERSION = '2.4.2.1';

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const MESSAGE_KEY = 'Nc2ToNc3ConnectionError';


/**
 * Controller with Flash component for error message
 *
 * @var string
 */
	private static $__controller = null;

/**
 * Setup migration from NetCommons2
 *
 * @param Controller $controller Controller with Flash component for error message
 * @param array $config The DataSource configuration settings
 * @return void
 */
	public static function migration(Controller $controller, $config) {
		static::$__controller = $controller;
		static::__createNc2Connection($config);
		if (!static::__validateNc2Connection()) {
			return false;
		}

		return true;
	}

/**
 * Creates a DataSource object for nc2
 *
 * @param array $config The DataSource configuration settings
 * @return void
 */
	private static function __createNc2Connection($config) {
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		$config += $nc3config;
		ConnectionManager::create(static::CONNECTION_NAME, $config);
	}

/**
 * Check DataSource object for nc2.
 *
 * @return bool True on it access to config table of nc2.
 */
	private static function __validateNc2Connection() {
		// DataSource情報が間違っている場合、
		// Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			$Nc2Config = ClassRegistry::init([
				'class' => 'Nc2Config',
				'table' => 'config',
				'ds' => static::CONNECTION_NAME
			]);

			$configData = $Nc2Config->findByConfName('version');
			if ($configData['Nc2Config']['conf_value'] != static::VALID_VERSION) {
				static::$__controller->Flash->set('This is a message');
				ConnectionManager::drop(static::CONNECTION_NAME);
				return false;
			}

		} catch (Exception $ex) {
			//static::$__controller->NetCommons->setFlashNotification('Connection information invalid',['interval' => NetCommonsComponent::ALERT_VALIDATE_ERROR_INTERVAL]);
			static::$__controller->Session->setFlash(
				__d('nc2_to_nc3', 'Connection information invalid.See the error.log.'),
				'default',
				['class' => 'alert alert-danger'],
				static::MESSAGE_KEY
			);
			CakeLog::error($ex);
			return false;
		}

		return true;
	}
}
