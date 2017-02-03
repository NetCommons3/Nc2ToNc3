<?php
/**
 * Nc2ToNc3UserRole
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3UserRole
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 *
 */
class Nc2ToNc3UserRole extends Nc2ToNc3AppModel {

/**
 * Custom database table name, or null/false if no table association is desired.
 *
 * @var string
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#usetable
 */
	public $useTable = false;

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Base'];

/**
 * Id map of nc2 and nc3.
 *
 * @var array
 */
	private $__idMap = null;

/**
 * Get id map
 *
 * @param string $nc2RoleAuthorityId Nc2Authority.id.
 * @return array|string Id map.
 */
	public function getIdMap($nc2RoleAuthorityId = null) {
		// データの移行はしない
		// Nc2Authority.idとNc3UserRoleSetting.role_keyの対応付けのみ行う
		// Nc2Authority.idをkeyにUserRoleSetting.role_keyと対応付ける
		// 直接変更することで、Nc2ToNc3User::__convertRoleより対応付けされるようになる
		// @see Nc2ToNc3User::__convertRole
		if (!$this->__idMap) {
			$this->__idMap = [
				'1' => [
					'UserRoleSetting' => [
						'role_key' => 'system_administrator'
					]
				]
			];
		}

		if (!isset($nc2RoleAuthorityId)) {
			return $this->__idMap;
		}

		// 対応データがなければcommon_userを返す
		$default = [
			'UserRoleSetting' => [
				'role_key' => 'common_user'
			]
		];

		return Hash::get($this->__idMap, [$nc2RoleAuthorityId], $default);
	}

}
