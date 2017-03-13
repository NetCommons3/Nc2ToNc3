<?php
/**
 * Nc2ToNc3DividedBaseConvertBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('ModelBehavior', 'Model');

/**
 * Nc2ToNc3DividedBaseConvertBehavior
 *
 */
class Nc2ToNc3DividedBaseConvertBehavior extends ModelBehavior {

/**
 * Convert nc2 date.
 *
 * @param Model $model Model using this behavior.
 * @param string $date Nc2 date.
 * @return Model converted date.
 */
	public function convertDate(Model $model = null, $date) {
		return $this->_convertDate($date);
	}

/**
 * Convert nc2 display_days.
 *
 * @param Model $model Model using this behavior.
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	public function convertDisplayDays(Model $model = null, $displayDays) {
		return $this->_convertDisplayDays($displayDays);
	}

/**
 * Convert nc2 choice value
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted nc3 value.
 */
	public function convertChoiceValue(Model $model = null, $nc2Value, $nc3Choices) {
		return $this->_convertChoiceValue($nc2Value, $nc3Choices);
	}

/**
 * Convert nc3 date.
 *
 * @param string $date Nc2 date.
 * @return string converted date.
 */
	protected function _convertDate($date) {
		if (strlen($date) != 14) {
			return null;
		}

		// YmdHis → Y-m-d H:i:s　
		$date = substr($date, 0, 4) . '-' .
				substr($date, 4, 2) . '-' .
				substr($date, 6, 2) . ' ' .
				substr($date, 8, 2) . ':' .
				substr($date, 10, 2) . ':' .
				substr($date, 12, 2);

		return $date;
	}

/**
 * Convert nc2 display_days.
 *
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	protected function _convertDisplayDays($displayDays) {
		if (!$displayDays) {
			return null;
		}
		$arr = [30, 14, 7, 3, 1];
		foreach ($arr as $num) {
			if ($displayDays >= $num) {
				$displayDays = $num;
				break;
			}
		}
		return $displayDays;
	}

/**
 * Convert nc2 choice value.
 *
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted nc3 value.
 */
	protected function _convertChoiceValue($nc2Value, $nc3Choices) {
		if (!$nc2Value) {
			return null;
		}

		$nc3Choices = rsort($nc3Choices, SORT_NUMERIC);
		foreach ($nc3Choices as $nc3Choice) {
			if ($nc2Value >= $nc3Choice) {
				$nc2Value = $nc3Choice;
				break;
			}
		}

		return $nc2Value;
	}

}
