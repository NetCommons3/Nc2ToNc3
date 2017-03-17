<?php
/**
 * Nc2ToNc3QuestionnaireBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3QuestionnaireBehavior
 *
 */
class Nc2ToNc3QuestionBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Convert nc2 question_type.
 *
 * @param Model $model Model using this behavior.
 * @param string $questionType Nc2 question_type.
 * @return string converted nc3 question_type.
 */
	public function convertQuestionType(Model $model, $questionType) {
		return $this->_convertQuestionType($questionType);
	}

/**
 * Get graph_color.
 *
 * @param Model $model Model using this behavior.
 * @param string $choiceSequence Choice sequence number.
 * @return string graph_color code
 */
	public function getGraphColor(Model $model, $choiceSequence) {
		return $this->_getGraphColor($choiceSequence);
	}

/**
 * Convert nc2 question_type.
 *
 * @param string $questionType Nc2 question_type.
 * @return string converted nc3 question_type.
 */
	protected function _convertQuestionType($questionType) {
		// QuestionnairesComponentとQuizzesComponentそれぞれで
		// TYPE_〇〇と定義されているが一緒にしてみた。べつべつがよければ要修正
		$map = [
			'0' => '1',
			'1' => '2',
			'2' => '4',
			'3' => '3',
		];

		return $map[$questionType];
	}

/**
 * Get graph_color.
 *
 * @param string $choiceSequence Choice sequence number.
 * @return string graph_color code
 */
	protected function _getGraphColor($choiceSequence) {
		$colors = [
			"#f38631",
			"#e0e4cd",
			"#69d2e7",
			"#68e2a7",
			"#f64649",
			"#4d5361",
			"#47bfbd",
			"#7c4f6c",
			"#23313c",
			"#9c9b7f",
			"#be5945",
			"#cccccc"
		];
		$choiceSequence = (int)$choiceSequence;

		return $colors[$choiceSequence];
	}

}