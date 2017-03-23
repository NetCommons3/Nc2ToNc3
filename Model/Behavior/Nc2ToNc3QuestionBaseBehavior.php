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
 * Convert answer value.
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2AnswerValue Nc2 answer value.
 * @param array $nc3Choices Nc3QuestionnaireChoice data.
 * @return string graph_color code
 */
	public function convertAnswerValue(Model $model, $nc2AnswerValue, $nc3Choices) {
		return $this->_convertAnswerValue($nc2AnswerValue, $nc3Choices);
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

/**
 * Convert answer value.
 *
 * @param string $nc2AnswerValue Nc2 answer value.
 * @param array $questionMap questionMap data.
 * @return string graph_color code
 */
	protected function _convertAnswerValue($nc2AnswerValue, $questionMap) {
		if (!$questionMap['QuestionnaireChoice']) {
			return $nc2AnswerValue;
		}

		$nc2ChoiceSequences = explode('|', $nc2AnswerValue);
		$nc3AnswerValues = '';
		$nc3AnswerArray = [];
		foreach ($nc2ChoiceSequences as $nc2ChoiceSequence) {
			if ($nc2ChoiceSequence == '0') {
				continue;
			}

			$nc3Sequence = (int)$nc2ChoiceSequence - 1;
			$nc3Choice = $nc3Choices[$nc3Sequence];

			// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/View/Helper/QuestionnaireAnswerHelper.php#L421-L428
			// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Behavior/QuestionnaireAnswerMultipleChoiceBehavior.php#L65
			// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Behavior/QuestionnaireAnswerBehavior.php#L68-L71
			$nc3AnswerValue = '|' . $nc3Choice['key'] . ':' . $nc3Choice['choice_label'];
			if ($questionMap['Nc2QuestionnaireQuestion']['question_type'] == '2') {
				$nc3AnswerArray[] = $nc3AnswerValue;
			}
		}

		if ($nc3AnswerArray) {
			return $nc3AnswerArray;
		}

		return $nc3AnswerValue;
	}

}