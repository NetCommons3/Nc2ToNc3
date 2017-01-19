<?php
/**
 * Nc2ToNc3UserAttribute
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3UserAttribute
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getConvertDate($date)
 *
 * @see Nc2ToNc3UserAttributeBaseBehavior
 * @method void putIdMap($nc2ItemId, $nc3UserAttributeId)
 * @method string getIdMap($nc2ItemId)
 * @method string getLanguageIdFromNc2()
 * @method string getNc2ItemValueByConstant($constant, $languageId)
 * @method string getNc2ItemDescriptionById($itemId)
 * @method bool isNc2AutoregistUseItem($itemId)
 * @method bool isNc2AutoregistUseItemRequire($itemId)
 * @method string getUserAttributeSettingRow()
 * @method string getUserAttributeSettingCol()
 * @method int getUserAttributeSettingWeight()
 * @method void incrementUserAttributeSettingWeight()
 *
 * @see Nc2ToNc3UserAttributeBehavior
 * @method string getLogArgument($nc2Item)
 * @method bool isMigrationRow($nc2Item)
 * @method void putExistingIdMap($nc2Item)
 * @method bool isChoiceRow($nc2Item)
 * @method bool isChoiceMergenceRow($nc2Item)
 *
 */
class Nc2ToNc3UserAttribute extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3UserAttribute'];

/**
 * Migration method.
 *
 * @return bool True on success
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'UserAttribute Migration start.'));

		// 不正データは移行処理をしないようにした
		//if (!$this->validates()) {
		//	return false;
		//}

		if (!$this->__saveUserAttributeFromNc2()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'UserAttribute Migration end.'));
		return true;
	}

/**
 * Save Nc3UserAttribue from Nc2.
 *
 * @return bool True on success
 */
	private function __saveUserAttributeFromNc2() {
		$Nc2Item = $this->getNc2Model('items');
		$query = [
			'order' => [
				'Nc2Item.col_num',
				'Nc2Item.row_num'
			]
		];
		$nc2Items = $Nc2Item->find('all', $query);
		$UserAttribute = ClassRegistry::init('UserAttributes.UserAttribute');

		// Nc2ToNc3UserAttributeBehavior::__getNc3UserAttributeIdByTagNameAndDataTypeKeyで
		// 'UserAttribute.id'を取得する際、TrackableBehaviorでUsersテーブルを参照する
		// $UserAttribute::begin()してしまうと、Usersテーブルがロックされ、
		// UserAttributeBehavior::UserAttributeBehavior()のALTER TABLEで待ち状態になる
		// (Waiting for table metadata lock)
		//$UserAttribute->begin();
		try {
			foreach ($nc2Items as $nc2Item) {
				if (!$this->isMigrationRow($nc2Item)) {
					continue;
				}

				$this->putExistingIdMap($nc2Item);
				$data = $this->__generateNc3Data($nc2Item);
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
				if ($this->getIdMap($nc2ItemId)) {
					continue;
				}

				$this->putIdMap($nc2ItemId, $UserAttribute->id);
				$this->incrementUserAttributeSettingWeight();
			}

			//$UserAttribute->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $UserAttribute::saveUserAttribute()でthrowされるとこの処理に入ってこない
			//$UserAttribute->rollback($ex);
		}

		return true;
	}

/**
 * Generate nc3 data
 *
 * Data sample
 * data[UserAttributeSetting][id]:
 * data[UserAttributeSetting][row]:1
 * data[UserAttributeSetting][col]:2
 * data[UserAttributeSetting][weight]:
 * data[UserAttributeSetting][display]:1
 * data[UserAttributeSetting][is_system]:0
 * data[UserAttributeSetting][user_attribute_key]:
 * data[UserAttribute][0][id]:
 * data[UserAttribute][0][key]:
 * data[UserAttribute][0][language_id]:1
 * data[UserAttribute][0][name]:
 * data[UserAttribute][1][id]:
 * data[UserAttribute][1][key]:
 * data[UserAttribute][1][language_id]:2
 * data[UserAttribute][1][name]:
 * data[UserAttributeSetting][display_label]:0
 * data[UserAttributeSetting][display_label]:1
 * data[UserAttributeSetting][data_type_key]:text
 * data[UserAttributeSetting][is_multilingualization]:0
 * data[UserAttributeSetting][is_multilingualization]:1
 * data[UserAttributeSetting][required]:0
 * data[UserAttributeSetting][only_administrator_readable]:0
 * data[UserAttributeSetting][only_administrator_editable]:0
 * data[UserAttributeSetting][self_public_setting]:0
 * data[UserAttributeSetting][self_email_setting]:0
 * data[UserAttribute][0][description]:
 * data[UserAttribute][1][description]:
 * data[UserAttributeChoice][1][1][id]:
 * data[UserAttributeChoice][1][1][language_id]:1
 * data[UserAttributeChoice][1][1][user_attribute_id]:
 * data[UserAttributeChoice][1][1][key]:
 * data[UserAttributeChoice][1][1][code]:
 * data[UserAttributeChoice][1][1][weight]:1
 * data[UserAttributeChoice][1][1][name]:
 * data[UserAttributeChoice][2][1][id]:
 * data[UserAttributeChoice][2][1][language_id]:1
 * data[UserAttributeChoice][2][1][user_attribute_id]:
 * data[UserAttributeChoice][2][1][key]:
 * data[UserAttributeChoice][2][1][code]:
 * data[UserAttributeChoice][2][1][weight]:2
 * data[UserAttributeChoice][2][1][name]:
 * data[UserAttributeChoice][3][1][id]:
 * data[UserAttributeChoice][3][1][language_id]:1
 * data[UserAttributeChoice][3][1][user_attribute_id]:
 * data[UserAttributeChoice][3][1][key]:
 * data[UserAttributeChoice][3][1][code]:
 * data[UserAttributeChoice][3][1][weight]:3
 * data[UserAttributeChoice][3][1][name]:
 * data[UserAttributeChoice][1][2][id]:
 * data[UserAttributeChoice][1][2][language_id]:2
 * data[UserAttributeChoice][1][2][user_attribute_id]:
 * data[UserAttributeChoice][1][2][key]:
 * data[UserAttributeChoice][1][2][code]:
 * data[UserAttributeChoice][1][2][weight]:1
 * data[UserAttributeChoice][1][2][name]:
 * data[UserAttributeChoice][2][2][id]:
 * data[UserAttributeChoice][2][2][language_id]:2
 * data[UserAttributeChoice][2][2][user_attribute_id]:
 * data[UserAttributeChoice][2][2][key]:
 * data[UserAttributeChoice][2][2][code]:
 * data[UserAttributeChoice][2][2][weight]:2
 * data[UserAttributeChoice][2][2][name]:
 * data[UserAttributeChoice][3][2][id]:
 * data[UserAttributeChoice][3][2][language_id]:2
 * data[UserAttributeChoice][3][2][user_attribute_id]:
 * data[UserAttributeChoice][3][2][key]:
 * data[UserAttributeChoice][3][2][code]:
 * data[UserAttributeChoice][3][2][[UserAttributeChoice]weight]:3
 * data[UserAttributeChoice][3][2][name]:
 *
 * @param array $nc2Item Nc2Item data.
 * @return array Nc3UserAttribute data.
 */
	private function __generateNc3Data($nc2Item) {
		$data = [];

		if (!$this->getIdMap($nc2Item['Nc2Item']['item_id'])) {
			return $this->__generateNc3UserAttributeData($nc2Item);
		}

		if (!$this->isChoiceMergenceRow($nc2Item)) {
			return $data;
		}

		return $this->__generateNc3UserAttributeDataMergedUserAttributeChoice($nc2Item);
	}

/**
 * Generate Nc3UserAttribute data.
 *
 * @param array $nc2Item Nc2Item data.
 * @return array Nc3UserAttribute data.
 */
	private function __generateNc3UserAttributeData($nc2Item) {
		$data = [];
		$nc2ItemId = $nc2Item['Nc2Item']['item_id'];
		$Language = ClassRegistry::init('M17n.Language');
		$UserAttribute = ClassRegistry::init('UserAttribute.UserAttribute');

		// 作成日時（created）は移行する？
		$created = $this->getConvertDate($nc2Item['Nc2Item']['insert_time']);
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
				'created' => $created

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
			'auto_regist_display' => $this->isNc2AutoregistUseItem($nc2ItemId),
			'created' => $created
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
					'created' => $created
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
 * Generate Nc3UserAttribute data merged Nc3UserAttributeChoice.
 *
 * @param array $nc2Item Nc2Item data.
 * @return array Nc3UserAttribute data merged Nc3UserAttributeChoice.
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
		$UserAttributeId = $this->getIdMap($nc2ItemId);
		$userAttribute = $UserAttribute->findById($UserAttributeId, 'key', null, -1);
		$userAttributeKey = $userAttribute['UserAttribute']['key'];

		$data = $UserAttribute->getUserAttribute($userAttributeKey);

		// UserAttributeChoiceMapデータ作成
		// see https://github.com/NetCommons3/UserAttributes/blob/3.0.1/View/Elements/UserAttributes/choice_edit_form.ctp#L14-L27
		//     https://github.com/NetCommons3/UserAttributes/blob/3.0.1/Model/UserAttributeChoice.php#L254
		$choiceMap = Hash::extract($data['UserAttributeChoice'], '{n}.{n}');
		foreach ($choiceMap as $choice) {
			$choiceId = $choice['id'];
			$data['UserAttributeChoiceMap'][$choiceId] = [
				'id' => $choiceId,
				'language_id' => $choice['language_id'],
				'user_attribute_id' => $choice['user_attribute_id'],
				'key' => $choice['key'],
				'code' => $choice['code'],
			];

			if ($choice['language_id'] != $this->getLanguageIdFromNc2()) {
				continue;
			}

			$key = array_search($choice['name'], $nc2ItemOptions);
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
					// 作成日時（created）は移行する？
					'created' => $this->getConvertDate($nc2Item['Nc2Item']['insert_time'])
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
 * Get Nc2ItemsOption options by id.
 *
 * @param string $itemId Nc2Item id.
 * @return array Nc2ItemsOption options.
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
