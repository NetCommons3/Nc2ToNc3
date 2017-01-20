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
 * @method string getConvertDate($date)
 *
 * @see Nc2ToNc3UserBaseBehavior
 * @method void putIdMap($nc2UserId, $nc3UserAttributeId)
 * @method string getIdMap($nc2UserId)
 *
 * @see Nc2ToNc3UserAttributeBehavior
 * @method string getLogArgument($nc2User)
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
		// Model::dataにfieldがないとvalidationされないためset
		// @see
		// https://github.com/cakephp/cakephp/blob/2.9.4/lib/Cake/Model/Validator/CakeValidationSet.php#L131
		$this->set('dummy');

		$this->validate = Hash::merge(
			$this->validate,
			[
				'dummy' => [
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

		/* @var $Nc2UsersItemsLink AppModel */
		$Nc2UsersItemsLink = $this->getNc2Model('users_items_link');
		while ($nc2Users = $Nc2User->find('all', $query)) {
			$nc2UserIds = Hash::extract($nc2Users, '{n}.Nc2User.user_id');
			$nc2UserItemLinks = $Nc2UsersItemsLink->findAllByUserId($nc2UserIds, null, null, -1);

			if (!$this->__saveUserFromNc2($nc2Users, $nc2UserItemLinks)) {
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
 * @param array $nc2UserItemLinks Nc2UsersItemsLink data
 * @return bool True on success
 */
	private function __saveUserFromNc2($nc2Users, $nc2UserItemLinks) {
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

				$path = '{n}.Nc2UsersItemsLink[user_id=' . $nc2User['Nc2User']['user_id'] . ']';
				$nc2User['Nc2UsersItemsLink'] = Hash::extract($nc2UserItemLinks, $path);
				$data = $this->__generateNc3Data($nc2User);
				if (!$data) {
					continue;
				}

				//continue;

				if (!$User->saveUser($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2User) . "\n" .
						var_export($User->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}
				/*

				$nc2ItemId = $nc2Item['Nc2Item']['item_id'];
				if (isset($this->mappingId[$nc2ItemId])) {
					continue;
				}

				$this->mappingId[$nc2ItemId] = $UserAttribute->id;
				$this->incrementUserAttributeSettingWeight();
				*/
			}

			//$UserAttribute->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $UserAttribute::saveUserAttribute()でthrowされるとこの処理に入ってこない
			//$UserAttribute->rollback($ex);
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
		$data = [];

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');
		$userId = $this->getIdMap($nc2User['Nc2User']['user_id']);
		if ($userId) {
			$data = $User->getUser($userId);
		} else {
			$data = $User->createUser();
		}

		$userFields = array_keys($data['User']);
		$userLanguageFields = array_keys($data['UsersLanguage'][0]);

		/* @var $Nc2ToNc3UserAttr Nc2ToNc3UserAttribute */
		$Nc2ToNc3UserAttr = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserAttribute');
		$attributeIdMap = $Nc2ToNc3UserAttr->getIdMap();

		foreach ($attributeIdMap as $nc2ItemId => $mapValue) {
			$nc2ItemContent = Hash::extract($nc2User, 'Nc2UsersItemsLink.{n}[item_id=' . $nc2ItemId . '].content');
			if (!$nc2ItemContent) {
				continue;
			}

			$nc2ItemContent = $nc2ItemContent[0];
			$userAttributeKey = $mapValue['key'];
			if (in_array($userAttributeKey, $userFields)) {
				$data['Users'][$userAttributeKey] = $nc2ItemContent;
				continue;
			}

			if (!in_array($userAttributeKey, $userLanguageFields)) {
				continue;
			}

			foreach ($data['UsersLanguage'] as &$usersLanguage) {
				$usersLanguage[$userAttributeKey] = $nc2ItemContent;
			}
		}

		return $data;
	}

}
