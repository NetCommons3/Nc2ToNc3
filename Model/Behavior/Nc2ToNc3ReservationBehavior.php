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
 * Nc2ToNc3ReservationBehavior
 */
class Nc2ToNc3ReservationBehavior extends Nc2ToNc3BaseBehavior {
/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $Nc2Reservation Array data of Nc2Reservation, Nc2ReservationPost.
 * @return string Log argument
 */

	public function getLogArgument(Model $model, $Nc2Reservation) {
		return $this->__getLogArgument($Nc2Reservation);
	}

/**
 * Get Log argument.
 *
 * @param array $Nc2Reservation Array data of Nc2CalendarManage, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	private function __getLogArgument($Nc2Reservation) {
		if (isset($Nc2Reservation['Nc2Reservation'])) {
			return 'Nc2Reservation ' .
				'journal_id:' . $Nc2Reservation['Nc2Reservation']['journal_id'];
		}

		if (isset($Nc2Reservation['Nc2ReservationBlock'])) {
			return 'Nc2ReservationBlock ' .
				'block_id:' . $Nc2Reservation['Nc2ReservationBlock']['block_id'];
		}

		if (isset($Nc2Reservation['Nc2ReservationPost'])) {
			return 'Nc2ReservationPost ' .
				'post_id:' . $Nc2Reservation['Nc2ReservationPost']['post_id'];
		}
	}
}