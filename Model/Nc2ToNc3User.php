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
						'rule' => array('existsRequireAttribute'),
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

		$Nc2User = $this->getNc2Model('users');
		$query = [
			'order' => [
				'Nc2User.insert_time Desc',
				'Nc2User.user_id'
			],
			'limit' => $limit,
			'offset' => 0,
		];

		$Nc2UsersItemsLink = $this->getNc2Model('users_items_link');
		while ($nc2Users = $Nc2User->find('all', $query)) {
			$nc2UserIds = Hash::extract($nc2Users, '{n}.Nc2User.user_id');
			$nc2UsersItemsLinks = $Nc2UsersItemsLink->findAllByUserId($nc2UserIds, null, null, -1);

			if ($this->__saveUserFromNc2($nc2Users, $nc2UsersItemsLinks)) {
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
 * @param array $nc2UsersItemsLinks Nc2UserItemsLink data
 * @return bool True on success
 */
	private function __saveUserFromNc2($nc2Users, $nc2UsersItemsLinks) {
		$UserAttribute = ClassRegistry::init('UserAttributes.UserAttribute');

		// Nc2ToNc3UserAttributeBehavior::__getNc3UserAttributeIdByTagNameAndDataTypeKeyで
		// 'UserAttribute.id'を取得する際、TrackableBehaviorでUsersテーブルを参照する
		// $UserAttribute::begin()してしまうと、Usersテーブルがロックされ、
		// UserAttributeBehavior::UserAttributeBehavior()のALTER TABLEで待ち状態になる
		// (Waiting for table metadata lock)
		//$UserAttribute->begin();
		try {
			foreach ($nc2Users as $nc2User) {
				if (!$this->isMigrationRow($nc2User)) {
					continue;
				}

				$this->putExistingIdMap($nc2User);
				/*
				continue;

				$data = $this->__generateNc3Data($nc2User);
				if (!$data) {
					continue;
				}

				if (!$UserAttribute->saveUserAttribute($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Item) . "\n" .
						var_export($UserAttribute->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

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
 * @param array $nc2Item nc2 item data
 * @return array Nc3 data
 */
	private function __generateNc3Data($nc2Item) {
		$data = [];

		$nc2ItemId = $nc2Item['Nc2Item']['item_id'];
		if (!isset($this->mappingId[$nc2ItemId])) {
			return $this->__generateNc3UserAttributeData($nc2Item);
		}

		if (!$this->isChoiceMergenceRow($nc2Item)) {
			return $data;
		}

		return $this->__generateNc3UserAttributeDataMergedUserAttributeChoice($nc2Item);
	}

/**
 * Generate nc3 User data
 *
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
 * data[UsersLanguage][0][profile]:一般０１
 * data[UsersLanguage][1][profile]:一般０１
 * data[UsersLanguage][0][search_keywords]:
 * data[UsersLanguage][1][search_keywords]:
 *
 * @param array $nc2Item nc2 item data
 * @return array Nc3 UserAttribute data
 */
	private function __generateNc3UserAttributeData($nc2Item) {
		$data = [];
		$nc2ItemId = $nc2Item['Nc2Item']['item_id'];
		$Language = ClassRegistry::init('M17n.Language');
		$UserAttribute = ClassRegistry::init('UserAttribute.UserAttribute');

		$languages = $Language->getLanguages();
		foreach ($languages as $language) {
			$nc2Name = $nc2Item['Nc2Item']['item_name'];
			$nc3LanguageId = $language['Language']['id'];
			$nc2Description = $this->getNc2ItemDescriptionById($nc2ItemId);

			$userAttribute = $UserAttribute->create(array(
				'id' => null,
				'language_id' => $nc3LanguageId,
				'name' => $this->getNc2ItemValueByConstant($nc2Name, $nc3LanguageId),
				'description' => $this->getNc2ItemValueByConstant($nc2Description, $nc3LanguageId),

			));

			$data['UserAttribute'][] = $userAttribute['UserAttribute'];
		}

		$dataTypeKey = $nc2Item['Nc2Item']['type'];
		$selfEmailSetting = $nc2Item['Nc2Item']['allow_email_reception_flag'];
		if (!in_array($dataTypeKey, ['email', 'mobile_email'])) {
			$selfEmailSetting = '0';
		}
		$required = $nc2Item['Nc2Item']['require_flag'];
		if ($this->isNc2AutoregistUseItemRequire($nc2ItemId)) {
			$required = '1';
		}
		$defaultSetting = [
			'data_type_key' => $dataTypeKey,
			'row' => $this->getUserAttributeSettingRow(),
			'col' => $this->getUserAttributeSettingCol(),
			'weight' => $this->getUserAttributeSettingWeight(),
			'required' => $required,
			'display' => $nc2Item['Nc2Item']['display_flag'],
			'only_administrator_readable' => '1',	// 移行後手動で設定させる
			'only_administrator_editable' => '1',	// 移行後手動で設定させる
			'self_public_setting' => $nc2Item['Nc2Item']['allow_public_flag'],
			'self_email_setting' => $selfEmailSetting,
			'is_multilingualization' => '0',
			'auto_regist_display' => $this->isNc2AutoregistUseItem($nc2ItemId)
		];
		$data += $UserAttribute->UserAttributeSetting->create($defaultSetting);

		if (!$this->isChoiceRow($nc2Item)) {
			$data['UserAttributeChoice'] = $UserAttribute->UserAttributeChoice->validateRequestData($data);
			return $data;
		}

		$nc2ItemOptions = $this->__getNc2ItemOptionsById($nc2ItemId);
		if (!$nc2ItemOptions) {
			$data = [];
			$message = __d('nc2_to_nc3', '%s is not migration.', $this->getLogArgument($nc2Item));
			$this->writeMigrationLog($message);

			return $data;
		}

		$userAttributeChoices = [];
		$weight = 0;
		foreach ($nc2ItemOptions as $option) {
			$weight++;
			foreach ($languages as $language) {
				$nc3LanguageId = $language['Language']['id'];
				$userAttributeChoice = [
					'language_id' => $nc3LanguageId,
					'name' => $option,
					'weight' => $weight,
				];
				$userAttributeChoice = $UserAttribute->UserAttributeChoice->create($userAttributeChoice);
				$userAttributeChoice = $userAttributeChoice['UserAttributeChoice'];
				$userAttributeChoices['UserAttributeChoice'][$weight][$nc3LanguageId] = $userAttributeChoice;
			}
		}
		$data += $userAttributeChoices;
		$data['UserAttributeChoice'] = $UserAttribute->UserAttributeChoice->validateRequestData($data);
		if (!$data['UserAttributeChoice']) {
			$data = [];
			$message = __d('nc2_to_nc3', '%s is not migration.', $this->getLogArgument($nc2Item));
			$this->writeMigrationLog($message);
		}

		return $data;
	}

/**
 * Generate nc3 UserAttribute data merged UserAttributeChoice
 *
 * @param array $nc2Item nc2 item data
 * @return array Nc3 UserAttribute data merged UserAttributeChoice
 */
	private function __generateNc3UserAttributeDataMergedUserAttributeChoice($nc2Item) {
		$data = [];
		$nc2ItemId = $nc2Item['Nc2Item']['item_id'];

		$nc2ItemOptions = $this->__getNc2ItemOptionsById($nc2ItemId);
		if (!$nc2ItemOptions) {
			$message = __d('nc2_to_nc3', '%s does not merge.', $this->getLogArgument($nc2Item));
			$this->writeMigrationLog($message);
			return $data;
		}

		$UserAttribute = ClassRegistry::init('UserAttribute.UserAttribute');
		$userAttribute = $UserAttribute->findById($this->mappingId[$nc2ItemId], 'key', null, -1);
		$userAttributeKey = $userAttribute['UserAttribute']['key'];

		$data = $UserAttribute->getUserAttribute($userAttributeKey);

		// UserAttributeChoiceMapデータ作成
		// see https://github.com/NetCommons3/UserAttributes/blob/3.0.1/View/Elements/UserAttributes/choice_edit_form.ctp#L14-L27
		//     https://github.com/NetCommons3/UserAttributes/blob/3.0.1/Model/UserAttributeChoice.php#L254
		$choiceMaps = Hash::extract($data['UserAttributeChoice'], '{n}.{n}');
		foreach ($choiceMaps as $choiceMap) {
			$choiceId = $choiceMap['id'];
			$data['UserAttributeChoiceMap'][$choiceId] = [
				'language_id' => $choiceMap['language_id'],
				'user_attribute_id' => $choiceMap['user_attribute_id'],
				'key' => $choiceMap['key'],
				'code' => $choiceMap['code'],
			];

			if ($choiceMap['language_id'] != $this->getLanguageIdFromNc2()) {
				continue;
			}

			$key = array_search($choiceMap['name'], $nc2ItemOptions);
			if ($key !== false) {
				unset($nc2ItemOptions[$key]);
			}
		}

		if (!$nc2ItemOptions) {
			// 差分選択肢無し
			$data = [];
			return $data;
		}

		$userAttributeChoices = [];
		$weight = count($data['UserAttributeChoice']);
		$Language = ClassRegistry::init('M17n.Language');
		$languages = $Language->getLanguages();
		foreach ($nc2ItemOptions as $option) {
			$weight++;
			foreach ($languages as $language) {
				$nc3LanguageId = $language['Language']['id'];
				$userAttributeChoice = [
					'language_id' => $nc3LanguageId,
					'name' => $option,
					'weight' => $weight,
				];
				$userAttributeChoice = $UserAttribute->UserAttributeChoice->create($userAttributeChoice);
				$userAttributeChoice = $userAttributeChoice['UserAttributeChoice'];
				$userAttributeChoices['UserAttributeChoice'][$weight][$nc3LanguageId] = $userAttributeChoice;
			}
		}
		$data['UserAttributeChoice'] += $userAttributeChoices['UserAttributeChoice'];
		$data['UserAttributeChoice'] = $UserAttribute->UserAttributeChoice->validateRequestData($data);
		if (!$data['UserAttributeChoice']) {
			$data = [];
			$message = __d('nc2_to_nc3', '%s is not migration.', $this->getLogArgument($nc2Item));
			$this->writeMigrationLog($message);
		}

		return $data;
	}

/**
 * Get Nc2 item options by id.
 *
 * @param string $itemId Nc2 item id
 * @return array Nc2 item options
 */
	private function __getNc2ItemOptionsById($itemId) {
		$Nc2ItemOption = $this->getNc2Model('items_options');
		$itemOptions = $Nc2ItemOption->findByItemId($itemId, 'options', null, -1);
		if (!$itemOptions) {
			return $itemOptions;
		}

		$itemOptions = explode('|', $itemOptions['Nc2ItemsOption']['options']);
		if (!end($itemOptions)) {
			array_pop($itemOptions);
		}

		$languageId = $this->getLanguageIdFromNc2();
		foreach ($itemOptions as $key => $option) {
			$itemOptions[$key] = $this->getNc2ItemValueByConstant($option, $languageId);
		}

		return $itemOptions;
	}

}
