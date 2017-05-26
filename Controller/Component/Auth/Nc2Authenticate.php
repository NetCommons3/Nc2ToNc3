<?php
/**
 * Nc2Authenticate
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('FormAuthenticate', 'Controller/Component/Auth');

/**
 * Nc2Authenticate
 *
 */
class Nc2Authenticate extends FormAuthenticate {

/**
 * Call parent::authenticate with md5 password
 *
 * @param CakeRequest $request The request that contains login information.
 * @param CakeResponse $response Unused response object.
 * @return mixed False on login failure. An array of User data on success.
 */
	public function authenticate(CakeRequest $request, CakeResponse $response) {
		$userModel = $this->settings['userModel'];
		list(, $model) = pluginSplit($userModel);
		$passwordfield = $this->settings['fields']['password'];
		$password = $request->data[$model][$passwordfield];
		$request->data[$model][$passwordfield] = md5($request->data[$model][$passwordfield]);

		$result = parent::authenticate($request, $response);
		if (!empty($result) && is_array($result)) {
			return $result;
		}

		$request->data[$model][$passwordfield] = $password;
		return $result;
	}

}
