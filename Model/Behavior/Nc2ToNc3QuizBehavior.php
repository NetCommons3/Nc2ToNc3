<?php
/**
 * Nc2ToNc3QuizBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3QuestionBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3QuizBehavior
 *
 */
class Nc2ToNc3QuizBehavior extends Nc2ToNc3QuestionBaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Quiz Array data of Nc2Quiz, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Quiz) {
		return $this->__getLogArgument($nc2Quiz);
	}

/**
 * Generate Nc3Quiz data.
 *
 * Data sample
 * data[Quiz][import_key]:
 * data[Quiz][export_key]:
 * data[Quiz][title]:
 * data[Quiz][estimated_time]:0
 * data[Quiz][passing_grade]:0
 * data[Quiz][is_repeat_allow]:0
 * data[Quiz][is_repeat_until_passing]:0
 * data[Quiz][is_page_random]:0
 * data[Quiz][is_correct_show]:0
 * data[Quiz][is_correct_show]:1
 * data[Quiz][is_total_show]:0
 * data[Quiz][is_answer_mail_send]:0
 * data[Quiz][answer_timing]:0
 * data[Quiz][answer_start_period]:
 * data[Quiz][answer_end_period]:
 * data[Quiz][total_show_timing]:0
 * data[Quiz][total_show_start_period]:
 * data[Quiz][is_no_member_allow]:0
 * data[Quiz][is_key_pass_use]:0
 * data[AuthorizationKey][authorization_key]:
 * data[Quiz][is_image_authentication]:0
 * data[QuizPage][0][page_sequence]:0
 * data[QuizPage][0][QuizQuestion][0][key]:
 * data[QuizPage][0][QuizQuestion][0][question_sequence]:0
 * data[QuizPage][0][QuizQuestion][0][question_value]:新規問題1
 * data[QuizPage][0][QuizQuestion][0][question_type]:1
 * data[QuizPage][0][QuizQuestion][0][is_choice_random]:0
 * data[QuizPage][0][QuizQuestion][0][is_choice_horizon]:0
 * data[QuizPage][0][QuizQuestion][0][allotment]:10
 * data[QuizPage][0][QuizQuestion][0][commentary]:
 * data[QuizPage][0][QuizQuestion][0][QuizChoice][0][key]:19d7cb6c3045c3c54415446e2a3c71ae
 * data[QuizPage][0][QuizQuestion][0][QuizChoice][0][choice_sequence]:0
 * data[QuizPage][0][QuizQuestion][0][QuizChoice][0][choice_label]:新規選択肢1
 * data[QuizPage][0][QuizQuestion][0][QuizCorrect][0][key]:
 * data[QuizPage][0][QuizQuestion][0][QuizCorrect][0][correct_sequence]:0
 * data[QuizPage][0][QuizQuestion][0][QuizCorrect][0][correct][]:新規選択肢1
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Quiz Nc2Quiz data.
 * @return array Nc3Quiz data.
 */
	public function generateNc3QuizData(Model $model, $nc2Quiz) {
		$nc2QuizId = $nc2Quiz['Nc2Quiz']['quiz_id'];
		$quizMap = $this->_getMap($nc2QuizId);
		if ($quizMap) {
			// 既存の場合
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['Quiz'] = [
			'key' => Hash::get($quizMap, ['Quiz', 'key']),
			'is_active' => '0',
			'status' => '3',
			'title' => $nc2Quiz['Nc2Quiz']['quiz_name'],
			// title_iconなかったのでコメントにしとく
			//'title_icon' => $this->_convertTitleIcon($nc2Quiz['Nc2Quiz']['icon_name']),
			'answer_timing' => '0',
			'is_no_member_allow' => $nc2Quiz['Nc2Quiz']['nonmember_flag'],
			'is_key_pass_use' => '0',
			'is_image_authentication' => $nc2Quiz['Nc2Quiz']['image_authentication'],
			'is_repeat_allow' => $nc2Quiz['Nc2Quiz']['repeat_flag'],
			'is_page_random' => '0',
			'perfect_score' => $nc2Quiz['Nc2Quiz']['perfect_score'],
			'is_correct_show' => $nc2Quiz['Nc2Quiz']['correct_flag'],
			'is_total_show' => $nc2Quiz['Nc2Quiz']['total_flag'],
			'is_answer_mail_send' => $nc2Quiz['Nc2Quiz']['mail_send'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Quiz['Nc2Quiz']),
			'created' => $this->_convertDate($nc2Quiz['Nc2Quiz']['insert_time']),
		];
		if ($nc2Quiz['Nc2Quiz']['status'] != '0') {
			$data['Quiz']['is_active'] = '1';
			$data['Quiz']['status'] = '1';
		}
		if ($nc2Quiz['Nc2Quiz']['status'] != '2') {
			$data['Quiz'] += [
				'answer_timing' => '1',
				'answer_end_period' => $this->_convertDate($nc2Quiz['Nc2Quiz']['insert_time']),
			];
		}
		if ($nc2Quiz['Nc2Quiz']['quiz_type'] == '3') {
			$data['Quiz']['is_page_random'] = '1';
		}

		$data['QuizPage'] = $this->__generateNc3QuizPageData($nc2Quiz);

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
		$data['Topic'] = [
			'plugin_key' => 'quizzes'
		];

		return $data;
	}

/**
 * Generate Nc3QuizFrameSetting data.
 *
 * Data sample
 * data[QuizFrameSetting][id]:
 * data[QuizFrameSetting][frame_key]:4a5733f403efb04b89149453b2c3ead1
 * data[QuizFrameSetting][display_type]:1
 * data[QuizFrameSetting][display_num_per_page]:10
 * data[QuizFrameSetting][sort_type]:Quiz.modified DESC
 * data[Single][QuizFrameDisplayQuiz][quiz_key]:0ba02955abaf89e75abd5308e518db21
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2QBlock Nc2QuizBlock data.
 * @return array Nc3QuizFrameSetting data.
 */
	public function generateNc3QuizFrameSettingData(Model $model, $nc2QBlock) {
		$nc2QuizId = $nc2QBlock['Nc2QuizBlock']['quiz_id'];
		$quizMap = $this->_getMap($nc2QuizId);
		if (!$quizMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2QBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2BlockId = $nc2QBlock['Nc2QuizBlock']['block_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('QuizFrameSetting', $nc2BlockId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		/* @var $QFrameSetting QuizFrameSetting */
		$QFrameSetting = ClassRegistry::init('Quizzes.QuizFrameSetting');
		$data = $QFrameSetting->getDefaultFrameSetting();
		$data['QuizFrameSetting']['id'] = Hash::get($mapIdList, [$nc2BlockId]);
		$data['QuizFrameSetting']['display_type'] = '0';
		$data['Single']['QuizFrameDisplayQuiz'] = [
			'quiz_key' => $quizMap['Quiz']['key']
		];

		return $data;
	}

/**
 * Generate Nc3QuizAnswerSummary data.
 *
 * Data sample
 * data[QuizAnswerSummary][answer_number]:1
 * data[QuizAnswerSummary][quiz_key]:10
 * data[QuizAnswerSummary][user_id]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2QSummary Nc2QuizSummary data.
 * @return array Nc3QuizAnswerSummary data.
 */
	public function generateNc3QuizAnswerSummaryData(Model $model, $nc2QSummary) {
		$nc2QuizId = $nc2QSummary['Nc2QuizSummary']['quiz_id'];
		$quizMap = $this->_getMap($nc2QuizId);
		if (!$quizMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2QSummary));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2SummaryId = $nc2QSummary['Nc2QuizSummary']['summary_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('QuizAnswerSummary', $nc2SummaryId);
		if ($mapIdList) {
			// 移行済み
			// 更新すると回答数とかおかしくなるので移行できない。いけるのか？
			return [];
		}

		$nc3AnswerStatus = '0';
		$nc3IsGradeFinished = '0';
		$nc3AnswerStartTime = $this->_convertDate($nc2QSummary['Nc2QuizSummary']['insert_time']);
		$nc3AnswerFinishTime = null;
		$nc3ElapsedSeconde = '0';
		if ($nc2QSummary['Nc2QuizSummary']['answer_flag'] == '1') {
			$nc3AnswerStatus = '2';
			$nc3IsGradeFinished = '1';
			$nc3AnswerFinishTime = $this->_convertDate($nc2QSummary['Nc2QuizSummary']['answer_time']);
			$nc3ElapsedSeconde = strtotime($nc3AnswerFinishTime) - strtotime($nc3AnswerStartTime);
		}

		$nc3AnswerNumber = '1';
		$nc3UserId = null;
		$nc3Created = null;
		if ($nc2QSummary['Nc2QuizSummary']['insert_user_id'] != '0') {
			/* @var $Nc2ToNc3User Nc2ToNc3User */
			$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
			$nc3AnswerNumber = $nc2QSummary['Nc2QuizSummary']['answer_number'];
			$nc3UserId = $Nc2ToNc3User->getCreatedUser($nc2QSummary['Nc2QuizSummary']);
			$nc3Created = $this->_convertDate($nc2QSummary['Nc2QuizSummary']['insert_time']);
		}

		// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizAnswerSummary.php#L280-L286
		$data['QuizAnswerSummary'] = [
			'answer_status' => $nc3AnswerStatus,
			'test_status' => '0',
			'answer_number' => $nc3AnswerNumber,
			'is_grade_finished' => $nc3IsGradeFinished,
			'summary_score' => $nc2QSummary['Nc2QuizSummary']['summary_score'],
			'passing_status' => '2',
			'answer_start_time' => $this->_convertDate($nc2QSummary['Nc2QuizSummary']['insert_time']),
			'answer_finish_time' => $nc3AnswerFinishTime,
			'elapsed_seconde' => $nc3AnswerFinishTime,
			'within_time_status' => '2',
			'quiz_key' => $quizMap['Quiz']['key'],
			'user_id' => $nc3UserId,
			'created_user' => $nc3UserId,
			'created' => $nc3Created,
		];

		return $data;
	}

/**
 * Generate Nc3QuizAnswerSummary data.
 *
 * Data sample
 * data[QuizAnswer][4679c5306a13c5cb148708ada3f1b11d][0][answer_value]:選択肢B
 * data[QuizAnswer][4679c5306a13c5cb148708ada3f1b11d][0][quiz_question_key]:4679c5306a13c5cb148708ada3f1b11d
 * data[QuizAnswer][4679c5306a13c5cb148708ada3f1b11d][0][id]:
 * data[QuizAnswer][2f1917b25cf0fe0fb25e1e8b245bc0fc][0][answer_value][]:選択肢B
 * data[QuizAnswer][2f1917b25cf0fe0fb25e1e8b245bc0fc][0][answer_value][]:選択肢C
 * data[QuizAnswer][2f1917b25cf0fe0fb25e1e8b245bc0fc][0][quiz_question_key]:2f1917b25cf0fe0fb25e1e8b245bc0fc
 * data[QuizAnswer][2f1917b25cf0fe0fb25e1e8b245bc0fc][0][id]:
 * data[QuizAnswer][9824d32b96f300943ece8721ea6390f8][0][answer_value]:単語解答
 * data[QuizAnswer][9824d32b96f300943ece8721ea6390f8][0][quiz_question_key]:9824d32b96f300943ece8721ea6390f8
 * data[QuizAnswer][9824d32b96f300943ece8721ea6390f8][0][id]:
 * data[QuizAnswer][00c5d5f08f81d32fc0515fffde6ea72a][0][answer_value]:記述式の回答
 * data[QuizAnswer][00c5d5f08f81d32fc0515fffde6ea72a][0][quiz_question_key]:00c5d5f08f81d32fc0515fffde6ea72a
 * data[QuizAnswer][00c5d5f08f81d32fc0515fffde6ea72a][0][id]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2QAnswers Nc2QuizAnswer data.
 * @param array $questionMap QuizQuestion map data.
 * @return array Nc3QuizAnswer data.
 */
	public function generateNc3QuizAnswerData(Model $model, $nc2QAnswers, $questionMap) {
		// Nc2ToNc3QuizBehavior::generateNc3QuizAnswerSummaryData でチェック済
		/*
		$nc2QuizId = $nc2QSummary['Nc2QuizSummary']['quiz_id'];
		$quizMap = $this->_getMap($nc2QuizId);
		if (!$quizMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2QBlock));
			$this->_writeMigrationLog($message);
			return [];
		}

		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2SummaryId = $nc2QSummary['Nc2QuizSummary']['summary_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('QuizAnswerSummary', $nc2SummaryId);
		if ($mapIdList) {
			// 移行済み
			// 更新すると回答数とかおかしくなるので移行できない。いけるのか？
			return [];
		}*/

		$data = [];
		foreach ($nc2QAnswers as $nc2QAnswer) {
			$nc2QuestionId = $nc2QAnswer['Nc2QuizAnswer']['question_id'];
			$nc3QuestionKey = $questionMap[$nc2QuestionId]['QuizQuestion']['key'];

			$nc2AnswerValue = $nc2QAnswer['Nc2QuizAnswer']['answer_value'];
			$nc3AnswerValue = $this->_convertAnswerValue($nc2AnswerValue, $questionMap[$nc2QuestionId]);

			// '0' のkeyがある
			// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/View/Helper/QuizAnswerHelper.php#L60
			$data['QuizAnswer'][$nc3QuestionKey]['0'] = [
				'quiz_question_key' => $nc3QuestionKey,
				'answer_value' => $nc3AnswerValue,
			];
		}

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Quiz Array data of Nc2Quiz, Nc2CalendarBlock and Nc2CalendarPlan.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Quiz) {
		if (isset($nc2Quiz['Nc2Quiz'])) {
			return 'Nc2Quiz ' .
				'quiz_id:' . $nc2Quiz['Nc2Quiz']['quiz_id'];
		}

		if (isset($nc2Quiz['Nc2QuizBlock'])) {
			return 'Nc2QuizBlock ' .
				'block_id:' . $nc2Quiz['Nc2QuizBlock']['block_id'];
		}

		if (isset($nc2Quiz['Nc2QuizSummary'])) {
			return 'Nc2QuizSummary ' .
					'summary_id:' . $nc2Quiz['Nc2QuizSummary']['summary_id'];
		}
	}

/**
 * Generate Nc3CalendarFrameSettingSelectRoom data.
 *
 * @param array $nc2Quiz Nc2Quiz data.
 * @return array Nc3QuizPage data.
 */
	private function __generateNc3QuizPageData($nc2Quiz) {
		/* @var $Nc2Question AppModel */
		$Nc2Question = $this->_getNc2Model('quiz_question');
		$nc2Questions = $Nc2Question->findAllByQuizId(
			$nc2Quiz['Nc2Quiz']['quiz_id'],
			null,
			'question_sequence',
			null,
			null,
			-1
		);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];
		$nc3PageSequence = 0;
		$nc3QuestionSequence = 0;
		foreach ($nc2Questions as $nc2Question) {
			$nc3Question = [];

			$nc2QuestionType = $nc2Question['Nc2QuizQuestion']['question_type'];
			$nc3Question = [
				'question_sequence' => $nc3QuestionSequence,
				'question_value' => $nc2Question['Nc2QuizQuestion']['question_value'],
				'question_type' => $this->_convertQuestionType($nc2QuestionType),
				'allotment' => $nc2Question['Nc2QuizQuestion']['allotment'],
				'commentary' => $nc2Question['Nc2QuizQuestion']['description'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Question['Nc2QuizQuestion']),
				'created' => $this->_convertDate($nc2Question['Nc2QuizQuestion']['insert_time']),
			];

			if ($nc2QuestionType != '2') {
				$nc3Question += $this->__generateNc3QuizChoiceCorrectData($nc2Question);
			}

			$data[$nc3PageSequence]['page_sequence'] = $nc3PageSequence;
			$data[$nc3PageSequence]['QuizQuestion'][$nc3QuestionSequence] = $nc3Question;

			if ($nc2Quiz['Nc2Quiz']['quiz_type'] == '1') {
				$nc3PageSequence++;
			} else {
				$nc3QuestionSequence++;
			}
		}

		return $data;
	}

/**
 * Generate Nc3QuizChoice and Nc3QuizCorrect data.
 *
 * @param array $nc2Question Nc2QuizQuestion data.
 * @return array Nc3QuizChoice data.
 */
	private function __generateNc3QuizChoiceCorrectData($nc2Question) {
		/* @var $Nc2Choice AppModel */
		$Nc2Choice = $this->_getNc2Model('quiz_choice');
		$nc2Choices = $Nc2Choice->findAllByQuestionId(
			$nc2Question['Nc2QuizQuestion']['question_id'],
			null,
			'choice_sequence',
			null,
			null,
			-1
		);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$choiceSequence = 0;
		// 複数単語のとき以外は正解は１番目のものだけが対象なので'0'をkeyにする
		// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/webroot/js/quizzes_edit_question.js#L726-L740
		$data['QuizCorrect']['0']['correct_sequence'] = '0';
		$nc2Corrects = explode('|', $nc2Question['Nc2QuizQuestion']['correct']);
		foreach ($nc2Choices as $nc2Choice) {
			$nc2ChoiceValue = $nc2Choice['Nc2QuizChoice']['choice_value'];
			if ($nc2Corrects[$choiceSequence] == '1') {
				$data['QuizCorrect']['0']['correct'][] = $nc2ChoiceValue;
			}

			if ($nc2Question['Nc2QuizQuestion']['question_type'] == '3') {
				$choiceSequence++;
				continue;
			}

			$data['QuizChoice'][] = [
				'choice_sequence' => $choiceSequence,
				'choice_label' => $nc2ChoiceValue,
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Choice['Nc2QuizChoice']),
				'created' => $this->_convertDate($nc2Choice['Nc2QuizChoice']['insert_time']),
			];

			$choiceSequence++;
		}

		return $data;
	}

/**
 * Get map
 *
 * @param array|string $nc2QuizIds Nc2CQuiz quiz_id.
 * @return array Map data with Nc2CQuiz quiz_id as key.
 */
	protected function _getMap($nc2QuizIds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Quiz Quiz */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Quiz = ClassRegistry::init('Quizzes.Quiz');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Quiz', $nc2QuizIds);
		$query = [
			'fields' => [
				'Quiz.id',
				'Quiz.key',
			],
			'conditions' => [
				'Quiz.id' => $mapIdList
			],
			'recursive' => -1,
			'callbacks' => false,
		];
		$nc3Quizzes = $Quiz->find('all', $query);
		if (!$nc3Quizzes) {
			return $nc3Quizzes;
		}

		$map = [];
		foreach ($nc3Quizzes as $nc3Quiz) {
			$nc2Id = array_search($nc3Quiz['Quiz']['id'], $mapIdList);
			$map[$nc2Id] = $nc3Quiz;
		}

		if (is_string($nc2QuizIds)) {
			$map = $map[$nc2QuizIds];
		}

		return $map;
	}

/**
 * Get question map
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2QSummary Nc2QuizSummary data.
 * @param array $nc3Quiz Nc3Quiz data.
 * @return array Map data with Nc2CQuiz quiz_id as key.
 */
	public function getQuestionMap(Model $model, $nc2QSummary, $nc3Quiz) {
		$nc2QuizId = $nc2QSummary['Nc2QuizSummary']['quiz_id'];

		// $Nc2Question::hasMany['Nc2QuizAnswer'] みたいな定義でいけそうだが個別に取得
		// @see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/Model.php#L893
		// @see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/Model.php#L1084

		/* @var $Nc2Question AppModel */
		/* @var $Nc2Choice AppModel */
		$Nc2Question = $this->_getNc2Model('quiz_question');
		$Nc2Choice = $this->_getNc2Model('quiz_choice');
		$nc2Questions = $Nc2Question->findAllByQuizId(
			$nc2QuizId,
			[
				'question_id',
				'question_sequence',
			],
			'question_sequence',
			null,
			null,
			-1
		);
		$query = [
			'fields' => [
				'choice_id',
				'choice_sequence',
				'question_id',
			],
			'conditions' => [
				'quiz_id' => $nc2QuizId
			],
			'order' => [
				'question_id',
				'choice_sequence',
			],
			'recursive' => -1,
		];
		$nc2ChoiceList = $Nc2Choice->find('list', $query);

		// $nc3QuizのQuizPage階層を除去QuizQuestion
		$nc3Questions = [];
		foreach ($nc3Quiz['QuizPage'] as $nc3QuestionsEachPage) {
			// 数値添字なのでarray_mergeで追加される
			$nc3Questions = array_merge($nc3Questions, $nc3QuestionsEachPage['QuizQuestion']);
		}

		// 対応チェック（あり得ない気がするが一応）
		if (count($nc2Questions) != count($nc3Questions)) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2QSummary));
			$this->_writeMigrationLog($message);

			return [];
		}

		$map = [];
		foreach ($nc2Questions as $key => $nc2Question) {
			$nc2QuestionId = $nc2Question['Nc2QuizQuestion']['question_id'];
			$map[$nc2QuestionId]['QuizQuestion'] = [
				'id' => $nc3Questions[$key]['id'],
				'key' => $nc3Questions[$key]['key'],
				'question_type' => $nc3Questions[$key]['question_type'],
			];

			if (!isset($nc2ChoiceList[$nc2QuestionId])) {
				continue;
			}

			$nc2ChoiceSeqList = $nc2ChoiceList[$nc2QuestionId];
			$nc3Choices = $nc3Questions[$key]['QuizChoice'];
			// 対応チェック（あり得ない気がするが一応）
			if (count($nc2ChoiceSeqList) != count($nc3Choices)) {
				$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2QSummary));
				$this->_writeMigrationLog($message);

				return [];
			}

			$map[$nc2QuestionId]['QuizChoice'] = $this->__getChoiceMap($nc2ChoiceSeqList, $nc3Choices);
			if (!$map[$nc2QuestionId]['QuizChoice']) {
				$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2QSummary));
				$this->_writeMigrationLog($message);

				return [];
			}
		}

		return $map;
	}

/**
 * Get choice map
 *
 * @param array $nc2ChoiceSeqList Nc2QuizChoice list data.
 * @param array $nc3Choices Nc3QuizChoice data.
 * @return array Map data with Nc2CQuiz quiz_id as key.
 */
	private function __getChoiceMap($nc2ChoiceSeqList, $nc3Choices) {
		$map = [];
		foreach ($nc2ChoiceSeqList as $nc2ChoiceSequence) {
			$nc3Choice = current($nc3Choices);

			// 対応チェック（あり得ない気がするが一応）
			// PHPMDでAvoid unused local variables such as '$nc2ChoiceSequence'.といわれることもあるため無理やり
			// そもそも、$nc3Choicesそのままでよい気もする
			if ((int)$nc2ChoiceSequence - 1 != $nc3Choice['choice_sequence']) {
				return [];
			}

			// $nc2ChoiceSequence順に0からの連番
			$map[] = [
				'id' => $nc3Choice['id'],
				'key' => $nc3Choice['key'],
				'choice_label' => $nc3Choice['choice_label'],
			];

			next($nc3Choices);
		}

		return $map;
	}

/**
 * Convert answer value.
 *
 * Nc2ToNc3QuestionBaseBehavior::_convertAnswerValue と同じで行けるかと思っていたが、
 * 微妙に違った（'|'がないとか）ので、overrideする
 *
 * @param string $nc2AnswerValue Nc2 answer value.
 * @param array $questionMap questionMap data.
 * @return string graph_color code
 */
	protected function _convertAnswerValue($nc2AnswerValue, $questionMap) {
		if (!isset($questionMap['QuizChoice'])) {
			return $nc2AnswerValue;
		}

		$nc2ChoiceSequences = explode('|', $nc2AnswerValue);
		$nc3Answers = [];
		foreach ($nc2ChoiceSequences as $key => $nc2ChoiceSequence) {
			if ($nc2ChoiceSequence == '0') {
				continue;
			}

			$nc3Choice = $questionMap['QuizChoice'][$key];
			$nc3Answers[] = $nc3Choice['choice_label'];
		}

		if ($questionMap['QuizQuestion']['question_type'] != '2') {
			return Hash::get($nc3Answers, [0]);
		}

		return $nc3Answers;
	}

}
