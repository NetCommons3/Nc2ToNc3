<?php
/**
 * Nc2ToNc3UserBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3UserBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3UserBehavior
 *
 */
class Nc2ToNc3UserBehavior extends Nc2ToNc3UserBaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2User Nc2User data.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2User) {
		return $this->__getLogArgument($nc2User);
	}

/**
 * Check migration target
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2User Nc2User data.
 * @return bool True if data is migration target.
 */
	public function isMigrationRow(Model $model, $nc2User) {
		// 承認待ち、本人確認待ちは移行しない（通知した承認用URLが違うため）
		// 移行して再度通知した方が良い気もする
		$active = $nc2User['Nc2User']['active_flag'];
		if (!in_array($active, ['0', '1'])) {
			$message = __d('nc2_to_nc3', '%s is not migration.', $this->__getLogArgument($nc2User));
			$this->_writeMigrationLog($message);
			return false;
		}

		return true;
	}

/**
 * Put existing id map
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Users Nc2User data
 * @return void
 */
	public function putExistingIdMap(Model $model, $nc2Users) {
		$loginIds = Hash::extract($nc2Users, '{n}.Nc2User.login_id');

		/* @var $User User */
		$User = ClassRegistry::init('Users.User');
		$query = [
			'fields' => [
				'User.username',
				'User.id',
			],
			'conditions' => [
				'User.username' => $loginIds
			],
			'recursive' => -1
		];
		$nc3Users = $User->find('list', $query);

		foreach ($nc2Users as $nc2User) {
			$loginId = $nc2User['Nc2User']['login_id'];
			if (isset($nc3Users[$loginId])) {
				$this->_putIdMap($nc2User['Nc2User']['user_id'], $nc3Users[$loginId]);
			}
		}
	}

/**
 * Check choice target
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Item nc2 item data
 * @return bool True if data is mergence target
 */
	public function isChoiceRow(Model $model, $nc2Item) {
		$choiceTypes = [
			'radio',
			'checkbox',
			'select'
		];
		if (!in_array($nc2Item['Nc2Item']['type'], $choiceTypes)) {
			return false;
		}

		return true;
	}

/**
 * Check choice mergence target
 *
 * @param Model $model Model using this behavior
 * @param array $nc2Item nc2 item data
 * @return bool True if data is mergence target
 */
	public function isChoiceMergenceRow(Model $model, $nc2Item) {
		if (!$this->isChoiceRow($model, $nc2Item)) {
			return false;
		}

		$notMergenceTagNames = [
			'lang_dirname_lang',
			'timezone_offset_lang',
			'role_authority_name',
			'active_flag_lang',
		];
		if (in_array($nc2Item['Nc2Item']['tag_name'], $notMergenceTagNames)) {
			return false;
		}

		return true;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2User Nc2User data
 * @return string Log argument
 */
	private function __getLogArgument($nc2User) {
		return 'Nc2User.user_id:' . $nc2User['Nc2User']['user_id'];
	}

}
