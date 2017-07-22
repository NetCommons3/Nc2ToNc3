<?php
/**
 * Nc2ToNc3ReservationBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3BlogBehavior
 * TODO Reservationにあわせる
 */

class Nc2ToNc3ReservationBehavior extends Nc2ToNc3BaseBehavior {
/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Journal Array data of Nc2Journal, Nc2JournalPost.
 * @return string Log argument
 */

	public function getLogArgument(Model $model, $nc2Journal) {
		return $this->__getLogArgument($nc2Journal);
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Journal Array data of Nc2CalendarManage, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Journal) {
		if (isset($nc2Journal['Nc2Journal'])) {
			return 'Nc2Journal ' .
				'journal_id:' . $nc2Journal['Nc2Journal']['journal_id'];
		}

		if (isset($nc2Journal['Nc2JournalBlock'])) {
			return 'Nc2JournalBlock ' .
				'block_id:' . $nc2Journal['Nc2JournalBlock']['block_id'];
		}

		if (isset($nc2Journal['Nc2JournalPost'])) {
			return 'Nc2JournalPost ' .
				'post_id:' . $nc2Journal['Nc2JournalPost']['post_id'];
		}
	}

}