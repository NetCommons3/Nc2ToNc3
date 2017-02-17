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
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
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
 * Map data.
 *
 * @var array
 */
	private $__map = null;

/**
 * Get map
 *
 * @param array|string $nc2RoleAuthorityIds Nc2Authority.id.
 * @return array Id map.
 */
	public function getMap($nc2RoleAuthorityIds = null) {
		// データの移行はしない
		// Nc2Authority.idとNc3UserRoleSetting.role_keyの対応付けのみ行う
		// Nc2Authority.idをkeyにUserRoleSetting.role_keyと対応付ける
		// 直接変更することで、Nc2ToNc3User::__convertFixedFieldで対応付けされるようになる
		// @see Nc2ToNc3User::__convertFixedField
		if (!$this->__map) {
			$this->__map = [
				'1' => [
					'UserRoleSetting' => [
						'role_key' => 'system_administrator'
					]
				]
			];
		}

		if (!isset($nc2RoleAuthorityIds)) {
			return $this->__map;
		}

		// 対応データがなければcommon_userを返す
		$default = [
			'UserRoleSetting' => [
				'role_key' => 'common_user'
			]
		];

		if (is_string($nc2RoleAuthorityIds)) {
			return Hash::get($this->__map, [$nc2RoleAuthorityIds], $default);
		}

		foreach ($nc2RoleAuthorityIds as $nc2RoleAuthorityId) {
			$map[$nc2RoleAuthorityId] = Hash::get($this->__map, [$nc2RoleAuthorityIds], $default);
		}

		return $map;
	}

}
