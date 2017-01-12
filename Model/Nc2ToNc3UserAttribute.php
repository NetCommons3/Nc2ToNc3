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
 * @method void setMigrationMessages($message)
 * @method string getMigrationMessages()
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 *
 * @see Nc2ToNc3UserAttributeBaseBehavior
 * @method string getLanguageIdFromNc2()
 * @method string getNc2ItemValueByConstant($constant, $languageId)
 * @method string getNc2ItemDescriptionById($itemId)
 * @method bool isNc2AutoregistUseItem($itemId)
 * @method bool isNc2AutoregistUseItemRequire($itemId)
 *
 * @see Nc2ToNc3UserAttributeBehavior
 * @method string getLogArgument($nc2Item)
 * @method bool isMigrationRow($nc2Item)
 * @method void mapExistingId($nc2Item)
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
 * Mapping nc2 id to nc3 id.
 * ユーザー情報で使う予定なのでpublic
 * behaviorにする？？？
 *
 * @var array
 */
	public $mappingId = [];

/**
 * Migration method.
 *
 * @return bool True on success
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'UserAttribute Migration start.'));

		if (!$this->__validateNc2()) {
			return false;
		}

		if (!$this->__validateNc3()) {
			return false;
		}

		if (!$this->__saveUserAttributeFromNc2()) {
			return false;
		}

		if (!$this->__setMappingDataNc2ToNc3()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'UserAttribute Migration end.'));
		return true;
	}

/**
 * Validate nc2 items data.
 *
 * @return bool True if nc2 data is valid.
 * @see Nc2ToNc3UserAttribute::__isMigrationRow()
 */
	private function __validateNc2() {
		// 不正データは移行処理をしないようにした
		return true;
	}

/**
 * Validate Nc3 UserAttribue data
 *
 * @return bool bool True if nc3 data is valid.
 * @see Nc2ToNc3UserAttribute::__isMigrationRow()
 */
	private function __validateNc3() {
		// Nc3に存在しなれば移行するようにした
		return true;
	}

/**
 * Save UserAttribue from Nc2.
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
		$notMigrationItems = [];
		$UserAttribute = ClassRegistry::init('UserAttributes.UserAttribute');

		$UserAttribute->begin();
		try {
			foreach ($nc2Items as $nc2Item) {
				if (!$this->isMigrationRow($nc2Item)) {
					continue;
				}

				$this->mapExistingId($nc2Item);
				$nc2ItemId = $nc2Item['Nc2Item']['item_id'];
				if (isset($this->mappingId[$nc2ItemId])) {
					$notMigrationItems[] = $nc2Item;
					continue;
				}

				$data = $this->__generateNc3Data($nc2Item);
				if (!$data) {
					continue;
				}
				//var_Dump($data['UserAttributeChoice']);
				//if (!$UserAttribute->saveUserAttribute($data)) {
					// error
				//}
			}

			foreach ($notMigrationItems as $nc2Item) {
				if (!$this->isChoiceMergenceRow($nc2Item)) {
					continue;
				}

				// Nc3に既存の選択肢データをマージする
				if (!$this->__saveMergedUserAttributeChoice($nc2Item)) {
					// error
				}
			}
			//var_dump($this->mappingId);
			$UserAttribute->commit();

		} catch (Exception $ex) {
			$UserAttribute->rollback($ex);
			//return false;
		}

		return true;
	}

/**
 * Set mapping data
 *
 * @return bool True on success
 */
	private function __setMappingDataNc2ToNc3() {
		return true;
	}

/**
 * Generate nc3 data
 *
 * data sample
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
 * @param array $nc2Item nc2 item data
 * @return array Nc3 data
 */
	private function __generateNc3Data($nc2Item) {
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
			'row' => '1',
			'col' => '2',
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
		$weight = 1;
		foreach ($nc2ItemOptions as $option) {
			$userAttributeChoice = [
				'name' => $option,
				'weight' => $weight,
			];
			$userAttributeChoice = $UserAttribute->UserAttributeChoice->create($userAttributeChoice);
			$userAttributeChoice = $userAttributeChoice['UserAttributeChoice'];

			foreach ($languages as $language) {
				$nc3LanguageId = $language['Language']['id'];
				$userAttributeChoices['UserAttributeChoice'][$weight][$nc3LanguageId] = $userAttributeChoice;
			}
			$weight++;
		}
		$data += $userAttributeChoices;

		return $data;
	}

/**
 * Save merged nc3 UserAttributeChoice data
 *
 * @param array $nc2Item nc2 item data
 * @return bool True on success
 */
	private function __saveMergedUserAttributeChoice($nc2Item) {
		$nc2ItemId = $nc2Item['Nc2Item']['item_id'];

		$nc2ItemOptions = $this->__getNc2ItemOptionsById($nc2ItemId);
		if (!$nc2ItemOptions) {
			$message = __d('nc2_to_nc3', '%s does not merge.', $this->getLogArgument($nc2Item));
			$this->writeMigrationLog($message);
			return false;
		}

		$UserAttribute = ClassRegistry::init('UserAttribute.UserAttribute');
		$query = [
			'fields' => 'UserAttributeChoice.name',
			'conditions' => [
				'UserAttributeChoice.user_attribute_id ' => $this->mappingId[$nc2ItemId],
				'UserAttributeChoice.language_id' => $this->getLanguageIdFromNc2()
			],
			'recursive' => -1
		];
		$userAttributeChoices = $UserAttribute->UserAttributeChoice->find('list', $query);
		if (!$userAttributeChoices) {
			$message = __d('nc2_to_nc3', '%s does not merge.', $this->getLogArgument($nc2Item));
			$this->writeMigrationLog($message);
			return false;
		}

		$userAttributeChoices = array_diff($userAttributeChoices, $nc2ItemOptions);
		if (!$userAttributeChoices) {
			// 差分選択肢無し
			return true;
		}

		//key取得
		//$data = $UserAttribute->getUserAttribute($key);
		// 差分を追加
		//UserAttributesController::editのput処理
		//$this->UserAttributeChoiceの直呼び出しの方が良い？

		return true;
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
