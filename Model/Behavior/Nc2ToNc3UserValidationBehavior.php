<?php
/**
 * Nc2ToNc3UserValidationBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3UserBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserValidationBehavior
 *
 */
class Nc2ToNc3UserValidationBehavior extends Nc2ToNc3UserBaseBehavior {

/**
 * Check require attribute
 *
 * @return string|bool True on it exists require attribute
 */
	public function existsRequireAttribute() {
		// NC3で必須入力の会員項目がNC2の会員項目にない場合、Userデータが必須エラーになるのでチェック
		// NC2に存在するが必須でない場合もチェックしとく(多くが未入力データと思われるため)
		// NC2の会員項目を途中から必須項目に変更すると、結局未入力のUserデータは必須エラーになるが、
		// 可能性は低いと考え、一応チェック
		// そもそも、会員項目移行時にNC3UserAttribute.requiredを更新しちゃえば良い気もする。

		// 移行しながら、必須エラーの件数がある程度発生したら止めた方が良い気がする
		// →対応済み
		// @see
		// https://github.com/NetCommons3/Nc2ToNc3/blob/6af9ac04a097738e10ea7efc0987b27e2aea6000/Model/Nc2ToNc3User.php#L140-L150

		/* @var $UserAttribute UserAttribute */
		$UserAttribute = ClassRegistry::init('UserAttributes.UserAttribute');
		$notCheckKeys = [
			'timezone',
			'role_key',
			'status',
			'language',
		];
		$query = [
			'fields' => 'UserAttribute.name',
			'conditions' => [
				'UserAttribute.language_id' => $this->_getLanguageIdFromNc2(),
				'NOT' => [
					'UserAttribute.key' => $notCheckKeys
				],
				'UserAttributeSetting.required' => '1'
			],
			'recursive' => 0
		];
		$nc3RequiredNames = $UserAttribute->find('list', $query);

		/* @var $Nc2Item AppModel */
		$Nc2Item = $this->_getNc2Model('items');
		$query = [
			'fields' => [
				'Nc2Item.item_id',
				'Nc2Item.item_name',
			],
			'conditions' => [
				'Nc2Item.require_flag' => '0'
			],
			'recursive' => -1
		];
		$nc2NotRequiredNames = $Nc2Item->find('list', $query);

		/* @var $Nc2ToNc3UserAttr Nc2ToNc3UserAttribute */
		$Nc2ToNc3UserAttr = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserAttribute');
		$attributeMap = $Nc2ToNc3UserAttr->getMap();
		foreach ($attributeMap as $nc2ItemId => $mapValue) {
			$userAttributeId = $mapValue['UserAttribute']['id'];
			if (isset($nc2NotRequiredNames[$nc2ItemId]) &&
				isset($nc3RequiredNames[$userAttributeId])
			) {
				continue;
			}

			unset($nc3RequiredNames[$userAttributeId]);
		}

		if (!empty($nc3RequiredNames)) {
			$message = __d('nc2_to_nc3', 'The require attribute of nc3 missing in nc2.') . "\n" .
				var_export($nc3RequiredNames, true);

			return $message;
		}

		return true;
	}

}
