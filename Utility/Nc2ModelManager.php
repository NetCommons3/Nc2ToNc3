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
	const VALID_VERSION = '2.4.2.11';

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const MESSAGE_KEY = 'Nc2ToNc3Error';

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

		if (!static::__createNc2Connection($config)) {
			return false;
		}

		if (!static::__validateNc2Connection()) {
			return false;
		}

		return true;
	}

/**
 * Creates a DataSource object for nc2
 *
 * @param array $config The DataSource configuration settings
 * @return bool True on it access to nc2 database
 */
	private static function __createNc2Connection($config) {
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		$config += $nc3config;

		// DataSource情報が間違っている場合、
		// Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			ConnectionManager::create(static::CONNECTION_NAME, $config);
		} catch (Exception $ex) {
			static::__setMessage($ex->getMessage());
			CakeLog::error($ex);
			return false;
		}

		return true;
	}

/**
 * Check DataSource object for nc2.
 *
 * @return bool True on it access to config table of nc2.
 */
	private static function __validateNc2Connection() {
		$Nc2Config = ClassRegistry::init([
			'class' => 'Nc2Config',
			'table' => 'config',
			'ds' => static::CONNECTION_NAME
		]);

		// DataSource情報(prefix)が間違っている場合、
		// Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			// 対象バージョンチェック
			$configData = $Nc2Config->findByConfName('version');
			if ($configData['Nc2Config']['conf_value'] != static::VALID_VERSION) {
				static::__setMessage(__d('nc2_to_nc3', 'NetCommons2 version is not %s', static::VALID_VERSION));
				ConnectionManager::drop(static::CONNECTION_NAME);
				return false;
			}

			// サイト閉鎖チェックはダンプデータをインポートしたDBを考慮するとしない方が良いのでは？
			// 運用中のDBを対象にしないことを推奨する
			//$configData = $Nc2Config->findByConfName('closesite');
		} catch (Exception $ex) {
			static::__setMessage(__d('nc2_to_nc3', 'NetCommons2 table is not found.'));
			CakeLog::error($ex);
			return false;
		}

		return true;
	}

/**
 * Set message with FlashComponent
 *
 * @param string $message Message.
 * @return bool True on it access to config table of nc2.
 */
	private static function __setMessage($message) {
		// 画面上部にalertをfadeさせる？
		//static::$__controller->NetCommons->setFlashNotification($message, ['interval' => NetCommonsComponent::ALERT_VALIDATE_ERROR_INTERVAL]);

		static::$__controller->Flash->set(
			$message,
			[
				'key' => static::MESSAGE_KEY,
				'params' => ['class' => 'alert alert-danger']
			]
		);
	}

/**
 * Get Nc2 Model
 *
 * @param string $name Nc2 model name. It must add Nc2 prefix.
 * @return void
 */
	public static function getModel($name) {
		ClassRegistry::getObject();
	}
}
