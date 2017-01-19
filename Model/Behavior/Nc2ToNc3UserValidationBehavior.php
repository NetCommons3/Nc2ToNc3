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
		// NC2の会員項目を途中から必須項目に変更すると、結局未入力のUserデータは必須エラーになるが、
		// 可能性は低いと考え、一応チェック
		// 移行しながら、必須エラーの件数がある程度発生したら止めた方が良い気がする

		$UserAttribute = ClassRegistry::init('UserAttributes.UserAttribute');
		$query = [
			'fields' => 'UserAttribute.name',	// いる？
			'conditions' => [
				'UserAttribute.language_id' => $this->_getLanguageIdFromNc2(),
				'UserAttributeSetting.required' => '1'
			],
			'recursive' => 0
		];
		$requiredNames = $UserAttribute->find('list', $query);

		/* @var $Nc2ToNc3UserAttr Nc2ToNc3UserAttribute */
		$Nc2ToNc3UserAttr = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3UserAttribute');
		$attributeIdMap = $Nc2ToNc3UserAttr->getIdMap();

		$notExistsNames = array_diff_key($requiredNames, array_flip($attributeIdMap));
		if (!empty($notExistsNames)) {
			$message = __d('nc2_to_nc3', 'The require attribute of nc3 missing in nc2.') . "\n" .
				var_export($notExistsNames, true);
			$this->_writeMigrationLog($message);

			return $message;
		}

		return true;
	}

}
