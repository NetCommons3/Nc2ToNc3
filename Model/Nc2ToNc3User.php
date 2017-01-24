<?php
/**
 * Nc2ToNc3User
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3User
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string convertDate($date)
 *
 * @see Nc2ToNc3UserBaseBehavior
 * @method void putIdMap($nc2UserId, $nc3UserId)
 * @method string getIdMap($nc2UserId)
 *
 * @see Nc2ToNc3UserAttributeBehavior
 * @method string getLogArgument($nc2User)
 * @method bool isApprovalWaiting($nc2User)
 * @method bool isMigrationRow($nc2User)
 * @method void putExistingIdMap($nc2User)
 *
 * @see Nc2ToNc3UserValidationBehavior
 * @method string|bool existsRequireAttribute($nc2User)
 */
class Nc2ToNc3User extends Nc2ToNc3AppModel {

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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3User',
		'Nc2ToNc3.Nc2ToNc3UserValidation'
	];

/**
 * Called during validation operations, before validation. Please note that custom
 * validation rules can be defined in $validate.
 *
 * @param array $options Options passed from Model::save().
 * @return bool True if validate operation should continue, false to abort
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#beforevalidate
 * @see Model::save()
 */
	public function beforeValidate($options = array()) {
		// Model::dataにfieldがないとvalidationされないためダミーフィールドをset
		// せっめーじ表示用にdatabeseという名前でset
		// @see
		// https://github.com/cakephp/cakephp/blob/2.9.4/lib/Cake/Model/Validator/CakeValidationSet.php#L131
		$this->set('database');

		$this->validate = Hash::merge(
			$this->validate,
			[
				'database' => [
					'existsRequireAttribute' => [
						'rule' => ['existsRequireAttribute'],
						// Nc2ToNc3UserValidationBehavior::existsRequireAttributeでメッセージを返す
						//'message' => __d('nc2_to_nc3', 'The require attribute of nc3 missing in nc2.'),
					],
				],
			]
		);

		return parent::beforeValidate($options);
	}

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'User Migration start.'));

		if (!$this->validates()) {
			return false;
		}

		if (!$this->__saveUserFromNc2WhileDividing()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'User Migration end.'));
		return true;
	}

/**
 * Save UserAttribue from Nc2 while dividing.
 *
 * @return bool True on success.
 */
	private function __saveUserFromNc2WhileDividing() {
		$limit = 1000;

		/* @var $Nc2User AppModel */
		$Nc2User = $this->getNc2Model('users');
		$query = [
			'order' => [
				'Nc2User.insert_time',
				'Nc2User.user_id'
			],
			'limit' => $limit,
			'offset' => 0,
		];

		while ($nc2Users = $Nc2User->find('all', $query)) {
			if (!$this->__saveUserFromNc2($nc2Users)) {
				return false;
			}
			$query['offset'] += $limit;
		}

		return true;
	}

/**
 * Save UserAttribue from Nc2.
 *
 * @param array $nc2Users Nc2User data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveUserFromNc2($nc2Users) {
		/* @var $User User */
		$User = ClassRegistry::init('Users.User');

		// Nc2ToNc3UserAttributeBehavior::__getNc3UserAttributeIdByTagNameAndDataTypeKeyで
		// 'UserAttribute.id'を取得する際、TrackableBehaviorでUsersテーブルを参照する
		// $UserAttribute::begin()してしまうと、Usersテーブルがロックされ、
		// UserAttributeBehavior::UserAttributeBehavior()のALTER TABLEで待ち状態になる
		// (Waiting for table metadata lock)
		//$UserAttribute->begin();
		try {
			$this->putExistingIdMap($nc2Users);
			foreach ($nc2Users as $nc2User) {
				if (!$this->isMigrationRow($nc2User)) {
					continue;
				}

				$data = $this->__generateNc3Data($nc2User);
				if (!$data) {
					continue;
				}

				/*var_dump($data);
				continue;
				if (!$User->saveUser($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2User) . "\n" .
						var_export($User->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}


				$nc2UserId = $nc2User['Nc2User']['user_id'];
				if ($this->getIdMap($nc2UserId)) {
					continue;
				}

				$this->putIdMap($nc2UserId, $User->data);
				*/
			}

			//$UserAttribute->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $UserAttribute::saveUserAttribute()でthrowされるとこの処理に入ってこない
			//$UserAttribute->rollback($ex);
			throw $ex;
		}

		//var_dump($this->getIdMap());
		return true;
	}

/**
 * Generate nc3 data
 *
 * Data sample
 * data[User][id]:
 * data[UsersLanguage][0][id]:
 * data[UsersLanguage][0][language_id]:1
 * data[UsersLanguage][0][user_id]:
 * data[UsersLanguage][1][id]:
 * data[UsersLanguage][1][language_id]:2
 * data[UsersLanguage][1][user_id]:
 * data[User][avatar]:
 * data[User][email]:example@example.com
 * data[User][moblie_mail]:
 * data[User][is_moblie_mail_public]:0
 * data[User][is_moblie_mail_reception]:0
 * data[User][is_moblie_mail_reception]:1
 * data[User][sex]:no_setting
 * data[User][language]:auto
 * data[User][timezone]:Asia/Tokyo
 * data[User][role_key]:common_user
 * data[User][status]:1
 * data[User][username]:example
 * data[User][handlename]:example
 * data[User][password]:
 * data[User][password_again]:
 * data[UsersLanguage][0][name]:
 * data[UsersLanguage][1][name]:
 * data[UsersLanguage][0][cd51d766e5064dad64a5e1b0e53abf8c]:
 * data[UsersLanguage][1][cd51d766e5064dad64a5e1b0e53abf8c]:
 * data[User][84fac6f6a28f3ef051de557435763859]:
 * data[User][1076aae36c3ff72f7134fc8d7d7a969c]:
 * data[User][794d482869bebcc788e32917cb24b3db]:
 * data[User][747f6e10210fc252fe81622fcd2ef213]:
 * data[User][7f4987d3cd25b8fc142c321f893720c7]:
 * data[User][42d7de57291c486872e205ee2744a063]:
 * data[UsersLanguage][0][profile]:
 * data[UsersLanguage][1][profile]:
 * data[UsersLanguage][0][search_keywords]:
 * data[UsersLanguage][1][search_keywords]:
 *
 * @param array $nc2User Nc2User data with Nc2UsersItemsLink data.
 * @return array Nc3UserAttribute data.
 */
	private function __generateNc3Data($nc2User) {
		// 作成者,更新者はユーザーデータ移行後に更新する？

		$data = [];

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');
		$nc2UserId = $nc2User['Nc2User']['user_id'];
		$idMap = $this->getIdMap($nc2UserId);
		if ($idMap) {
			$data = $User->getUser($idMap['User']['id']);
		} else {
			$data = $User->createUser();
		}

		// User.activate_key,User.activatedは会員項目データ（Nc2Item）に存在しないので固定で設定
		if ($this->isApprovalWaiting($nc2User)) {
			$data['User']['activate_key'] = $nc2User['Nc2User']['activate_key'];
			$data['User']['activated'] = time();
		}

		/* @var $Nc2ToNc3UserAttr Nc2ToNc3UserAttribute */
		/* @var $Nc2UsersItemsLink AppModel */
		$Nc2ToNc3UserAttr = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserAttribute');
		$Nc2UsersItemsLink = $this->getNc2Model('users_items_link');

		$userAttributeMap = $Nc2ToNc3UserAttr->getIdMap();
		$nc2UserItemLink = $Nc2UsersItemsLink->findAllByUserId($nc2UserId, null, null, -1);
		$nc3UserFields = array_keys($data['User']);
		$nc3LanguageFields = array_keys($data['UsersLanguage'][0]);
		foreach ($userAttributeMap as $nc2ItemId => $map) {
			$userAttributeKey = $map['UserAttribute']['key'];

			$nc3User = $this->__generateNc3User($userAttributeKey, $data['User'], $nc2User);
			if ($nc3User) {
				$data['User'] = $nc3User;
				continue;
			}

			$nc2ItemContent = $this->__getNc2ItemContent($nc2ItemId, $nc2UserItemLink);
			if ($Nc2ToNc3UserAttr->isChoice($map['UserAttributeSetting']['data_type_key'])) {
				$nc2ItemContent = $this->__getChoiceCode($nc2ItemContent, $map['UserAttributeChoice']);
			}

			if (in_array($userAttributeKey, $nc3UserFields)) {
				$data['User'][$userAttributeKey] = $nc2ItemContent;
				continue;
			}

			if (!in_array($userAttributeKey, $nc3LanguageFields)) {
				continue;
			}

			foreach ($data['UsersLanguage'] as &$usersLanguage) {
				$usersLanguage[$userAttributeKey] = $nc2ItemContent;
			}
		}

		return $data;
	}

/**
 * Generate Nc3Userdata
 *
 * @param string $userAttributeKey Nc3UserAttribute.key.
 * @param array $nc3User Nc3User data.
 * @param array $nc2User Nc2User data with Nc2UsersItemsLink data.
 * @return array Nc3UserAttribute data.
 */
	private function __generateNc3User($userAttributeKey, $nc3User, $nc2User) {
		// 登録者、変更者はまだ存在しない
		// 変更日時は移行した日時
		$notMigrationFiels = [
			'created_user',
			'modified',
			'modified_user'
		];
		if (in_array($userAttributeKey, $notMigrationFiels)) {
			return $nc3User;
		}

		$nc2UserFieldMap = [
			'login_id' => 'username',
			'password' => 'password',
			'handle' => 'handlename',
			'role_authority_id' => 'role_key',
			'active_flag' => 'status',
			'lang_dirname' => 'language',
			'timezone_offset' => 'timezone',
			'password_regist_time' => 'password_modified',
			'last_login_time' => 'last_login',
			'previous_login_time' => 'previous_login',
			'insert_time' => 'created',
		];

		$nc2Field = array_search($userAttributeKey, $nc2UserFieldMap);
		// 既存データは固定項目の内容を更新しない
		if (isset($nc3User['id']) &&
			$nc2Field
		) {
			return $nc3User;
		}
		if (!$nc2Field) {
			return [];
		}

		$dateFields = [
			'password_regist_time',
			'last_login_time',
			'previous_login_time',
			'insert_time',
			'update_time',
		];
		if (in_array($nc2Field, $dateFields)) {
			$nc3User[$userAttributeKey] = $this->convertDate($nc2User['Nc2User'][$nc2Field]);
			return $nc3User;
		}

		if ($nc2Field == 'role_authority_id') {
			$nc3User[$userAttributeKey] = $this->__convertRole($nc2User['Nc2User'][$nc2Field]);
			return $nc3User;
		}

		if ($nc2Field == 'lang_dirname') {
			$nc3User[$userAttributeKey] = $this->__convertLanguage($nc2User['Nc2User'][$nc2Field]);
			return $nc3User;
		}

		if ($nc2Field == 'timezone_offset') {
			$nc3User[$userAttributeKey] = $this->__convertTimezone($nc2User['Nc2User'][$nc2Field]);
			return $nc3User;
		}

		$nc3User[$userAttributeKey] = $nc2User['Nc2User'][$nc2Field];

		return $nc3User;
	}

/**
 * Convert role
 *
 * @param string $nc2RoleAuthorityId Nc2User.role_authority_id.
 * @return string Nc3UserRoleSetting.role_key.
 */
	private function __convertRole($nc2RoleAuthorityId) {
		/* @var $Nc2ToNc3UserRole Nc2ToNc3UserRole */
		$Nc2ToNc3UserRole = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserRole');
		$userRole = $Nc2ToNc3UserRole->getIdMap($nc2RoleAuthorityId);

		return $userRole['UserRoleSetting']['role_key'];
	}

/**
 * Convert language
 *
 * @param string $nc2LangDirname Nc2User.lang_dirname.
 * @return string Nc3User.language.
 */
	private function __convertLanguage($nc2LangDirname) {
		switch ($nc2LangDirname) {
			case 'japanese':
				$code = 'ja';
				break;

			case 'english':
				$code = 'en';
				break;

			default:
				$code = 'auto';

		}

		return $code;
	}

/**
 * Convert timezone
 *
 * @param string $nc2TimezoneOffset Nc2User.timezone_offset.
 * @return string Nc3User.timezone.
 */
	private function __convertTimezone($nc2TimezoneOffset) {
		$timezoneMap = [
			'-12.0' => 'Pacific/Kwajalein',
			'-11.0' => 'Pacific/Midway',
			'-10.0' => 'Pacific/Honolulu',
			'-9.0' => 'America/Anchorage',
			'-8.0' => 'America/Los_Angeles',
			'-7.0' => 'America/Denver',
			'-6.0' => 'America/Chicago',
			'-5.0' => 'America/New_York',
			'-4.0' => 'America/Dominica',
			'-3.5' => 'America/St_Johns',
			'-3.0' => 'America/Argentina/Buenos_Aires',
			'-2.0' => 'Atlantic/South_Georgia',
			'-1.0' => 'Atlantic/Azores',
			'0.0' => 'UTC',
			'1.0' => 'Europe/Brussels',
			'2.0' => 'Europe/Athens',
			'3.0' => 'Asia/Baghdad',
			'3.5' => 'Asia/Tehran',
			'4.0' => 'Asia/Muscat',
			'4.5' => 'Asia/Kabul',
			'5.0' => 'Asia/Karachi',
			'5.5' => 'Asia/Kolkata',
			'6.0' => 'Asia/Dhaka',
			'7.0' => 'Asia/Bangkok',
			'8.0' => 'Asia/Singapore',
			'9.0' => 'Asia/Tokyo',
			'9.5' => 'Australia/Darwin',
			'10.0' => 'Asia/Vladivostok',
			'11.0' => 'Australia/Sydney',
			'12.0' => 'Asia/Kamchatka'
		];

		return Hash::get($timezoneMap, [$nc2TimezoneOffset], 'Asia/Tokyo');
	}

/**
 * GetNc2ItemContent
 *
 * @param string $nc2ItemId Nc2Item item_id.
 * @param array $nc2UserItemLink Nc2UsersItemsLink data
 * @return string Nc2UsersItemsLink.content.
 */
	private function __getNc2ItemContent($nc2ItemId, $nc2UserItemLink) {
		$path = '{n}.Nc2UsersItemsLink[item_id=' . $nc2ItemId . '].content';
		$nc2ItemContent = Hash::extract($nc2UserItemLink, $path);
		if (!$nc2ItemContent) {
			return '';
		}

		return $nc2ItemContent[0];
	}

/**
 * GetNc2ItemContent
 *
 * @param string $nc2Content Nc2UsersItemsLink.content.
 * @param array $nc3Choices Nc3UserAttributeChoice data.
 * @return string Nc3UserAttributeChoice.code.
 */
	private function __getChoiceCode($nc2Content, $nc3Choices) {
		$nc2Contents = explode('|', $nc2Content);
		$choiceCode = '';
		foreach ($nc2Contents as $nc2Choice) {
			if ($nc2Choice === '') {
				$path = '{n}[code=no_setting]';
				$nc3Choice = Hash::extract($nc3Choices, $path);
				if ($nc3Choice) {
					$choiceCode .= $nc3Choice[0]['code'] . "\n";
				}

				continue;
			}

			$path = '{n}[name=' . $nc2Choice . ']';
			$nc3Choice = Hash::extract($nc3Choices, $path);
			if ($nc3Choice) {
				$choiceCode .= $nc3Choice[0]['code'] . "\n";
				continue;
			}

		}

		return rtrim($choiceCode);
	}

}
