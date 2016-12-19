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
 * Creates a DataSource object for nc2
 *
 * @param array $config The DataSource configuration settings
 * @return void
 */
	public static function createNc2Connection($config) {
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
	public static function validateNc2Connection() {
		// DataSource情報が間違っている場合、
		// Exception が発生するのでハンドリングできない
		// Try{}catch{}やる？

		$Nc2Config = ClassRegistry::init([
			'class' => 'Nc2Config',
			'table' => 'config',
			'ds' => static::CONNECTION_NAME
		]);

		$configData = $Nc2Config->findByConfName('version');
		if ($configData['Nc2Config']['conf_value'] != static::VALID_VERSION) {
			ConnectionManager::drop(static::CONNECTION_NAME);
			return false;
		}

		return true;
	}
}
