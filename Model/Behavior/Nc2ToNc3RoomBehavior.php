<?php
/**
 * Nc2ToNc3RoomBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3RoomBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3RoomBehavior
 *
 */
class Nc2ToNc3RoomBehavior extends Nc2ToNc3RoomBaseBehavior {

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
		// とりあえず移行しとく
		if ($this->__isApprovalWaiting($nc2User)) {
			$message = __d('nc2_to_nc3', '%s is not active.Resend approval mail.', $this->__getLogArgument($nc2User));
			$this->_writeMigrationLog($message);
			return true;
		}

		return true;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Page Nc2Page data
 * @return string Log argument
 */
	private function __getLogArgument($nc2Page) {
		return 'Nc2Page ' .
			'page_id:' . $nc2Page['Nc2User']['page_id'] .
			'page_name:' . $nc2Page['Nc2User']['page_name'];
	}

}
