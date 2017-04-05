<?php
/**
 * Nc2ToNc3Quiz
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Quiz
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
 * @method void changeNc3CurrentLanguage($langDirName = null)
 * @method void restoreNc3CurrentLanguage()
 * @method string convertChoiceValue($nc2Value, $nc3Choices)
 * @method string convertTitleIcon($titleIcon)
 * @method string convertTimezone($timezoneOffset)
 *
 * @see Nc2ToNc3QuestionBaseBehavior
 * @method string convertQuestionType($questionType)
 *
 * @see Nc2ToNc3QuizBehavior
 * @method string getLogArgument($nc2Quiz)
 * @method array generateNc3QuizData($nc2Quiz)
 * @method array generateNc3QuizFrameSettingData($nc2QBlock)
 * @method array generateNc3QuizAnswerSummaryData($nc2QSummary)
 * @method array generateNc3QuizAnswerData($nc2QAnswers)
 * @method array getQuestionMap($nc2QuizId, $nc3QuizKey)
 *
 */
class Nc2ToNc3Quiz extends Nc2ToNc3AppModel {

/**
 * Custom database table name, or null/false if no table association is desired.
 *
 * @var string
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#usetable
 */
	public $useTable = false;

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Quiz'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Quiz Migration start.'));

		/* @var $Nc2Quiz AppModel */
		$Nc2Quiz = $this->getNc2Model('quiz');
		$nc2Quizzes = $Nc2Quiz->find('all');
		if (!$this->__saveQuizFromNc2($nc2Quizzes)) {
			return false;
		}

		/* @var $Nc2QBlock AppModel */
		$Nc2QBlock = $this->getNc2Model('quiz_block');
		$nc2QBlocks = $Nc2QBlock->find('all');
		if (!$this->__saveQuizFrameSettingFromNc2($nc2QBlocks)) {
			return false;
		}

		/* @var $Nc2QSummary AppModel */
		$Nc2QSummary = $this->getNc2Model('quiz_summary');
		$query = [
			'order' => [
				'quiz_id',
				'answer_number',
			],
			'recursive' => -1,
		];
		$nc2QSummaries = $Nc2QSummary->find('all', $query);
		if (!$this->__saveQuizAnswerSummaryFromNc2($nc2QSummaries)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Quiz Migration end.'));
		return true;
	}

/**
 * Save Quiz from Nc2.
 *
 * @param array $nc2Quizzes Nc2Quiz data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuizFromNc2($nc2Quizzes) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Quiz data Migration start.'));

		/* @var $Quiz Quiz */
		/* @var $Nc2Quiz AppModel */
		/* @var $Frame Frame */
		$Quiz = ClassRegistry::init('Quizzes.Quiz');
		$Nc2QBlock = $this->getNc2Model('quiz_block');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2Quizzes as $nc2Quiz) {
			$Quiz->begin();
			try {
				$data = $this->generateNc3QuizData($nc2Quiz);
				if (!$data) {
					$Quiz->rollback();
					continue;
				}

				$nc2RoomId = $nc2Quiz['Nc2Quiz']['room_id'];
				$nc2QBlock = $Nc2QBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2QBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Quiz));
					$this->writeMigrationLog($message);
					$Quiz->rollback();
					continue;
				}

				// PHPMD.ExcessiveMethodLength になるので、別メソッドにした。
				if (!$this->__setCurrentData($nc2QBlock)) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Quiz));
					$this->writeMigrationLog($message);
					$Quiz->rollback();
					continue;
				}

				// Model::idを初期化しないとUpdateになってしまう。
				// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/Quiz.php#L442
				// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizSetting.php#L129-L149
				$Frame->create();

				if (!$Quiz->saveQuiz($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Quiz->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Quiz) . "\n" .
						var_export($Quiz->validationErrors, true);
					$this->writeMigrationLog($message);

					$Quiz->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				// 別メソッドにしたため、ここじゃできなくなった。
				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_editable']['value']);

				$nc2QuizId = $nc2Quiz['Nc2Quiz']['quiz_id'];
				$idMap = [
					$nc2QuizId => $Quiz->id
				];
				$this->saveMap('Quiz', $idMap);

				$Quiz->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuizFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Quiz->rollback($ex);
				throw $ex;
			}
		}

		// PHPMD.ExcessiveMethodLength になるので、別メソッドにした。
		$this->__unSetCurrentData();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Quiz data Migration end.'));

		return true;
	}

/**
 * Save QuizFrameSetting from Nc2.
 *
 * @param array $nc2QBlocks Nc2QuizBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuizFrameSettingFromNc2($nc2QBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuizFrameSetting data Migration start.'));

		/* @var $QFrameSetting QuizFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Block Block */
		$QFrameSetting = ClassRegistry::init('Quizzes.QuizFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		foreach ($nc2QBlocks as $nc2QBlock) {
			$QFrameSetting->begin();
			try {
				$data = $this->generateNc3QuizFrameSettingData($nc2QBlock);
				if (!$data) {
					$QFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2QBlock['Nc2QuizBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2QBlock));
					$this->writeMigrationLog($message);
					$QFrameSetting->rollback();
					continue;
				}
				// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizFrameDisplayQuiz.php#L221
				Current::write('Frame.key', $frameMap['Frame']['key']);

				// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizFrameSetting.php#L165-L167
				// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/Quiz.php#L464
				$nc3Block = $Block->findByRoomIdAndPluginKey(
					$frameMap['Frame']['room_id'],
					'quizzes',
					'id',
					null,
					-1
				);
				Current::write('Block.id', $nc3Block['Block']['id']);

				if (!$QFrameSetting->saveFrameSettings($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2QBlock) . "\n" .
						var_export($QFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$QFrameSetting->rollback();
					continue;
				}

				$idMap = [
					$nc2BlockId => $QFrameSetting->id
				];
				$this->saveMap('QuizFrameSetting', $idMap);

				$QFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuizFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$QFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuizFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save QuizAnswerSummary from Nc2.
 *
 * @param array $nc2QSummaries Nc2QuizSummary data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuizAnswerSummaryFromNc2($nc2QSummaries) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuizAnswerSummary data Migration start.'));

		/* @var $QAnswerSummary QuizAnswerSummary */
		/* @var $Quiz Quiz */
		$QAnswerSummary = ClassRegistry::init('Quizzes.QuizAnswerSummary');
		$Quiz = ClassRegistry::init('Quizzes.Quiz');
		$nc2PreviousQId = null;
		foreach ($nc2QSummaries as $nc2QSummary) {
			$QAnswerSummary->begin();
			try {
				$data = $this->generateNc3QuizAnswerSummaryData($nc2QSummary);
				if (!$data) {
					$QAnswerSummary->rollback();
					continue;
				}

				// Model::idを初期化しないと$QAnswerSummary::data[id]がセットされず、MailQueueBehaviorでNotice Errorになってしまう。
				// @see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/Model.php#L1962-L1964
				// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizAnswerSummary.php#L35
				// @see https://github.com/NetCommons3/Mails/blob/3.1.0/Model/Behavior/MailQueueBehavior.php#L409
				$QAnswerSummary->create();

				if (!($data = $QAnswerSummary->save($data))) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2QSummary) . "\n" .
						var_export($QAnswerSummary->validationErrors, true);
					$this->writeMigrationLog($message);

					$QAnswerSummary->rollback();
					continue;
				}

				$nc2QSummaryId = $nc2QSummary['Nc2QuizSummary']['summary_id'];
				$idMap = [
					$nc2QSummaryId => $QAnswerSummary->id
				];
				$this->saveMap('QuizAnswerSummary', $idMap);

				// Nc2QuizSummary.quiz_id 毎に
				// 対応するQuizQuestion,QuizChoiceをまとめて取得する
				$nc2CurrentQId = $nc2QSummary['Nc2QuizSummary']['quiz_id'];
				if ($nc2CurrentQId != $nc2PreviousQId) {

					$quizMap = $this->getMap($nc2CurrentQId);
					// 移行後に修正されることを考慮し、対応するidでQuizデータを取得
					// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Controller/QuizAnswersController.php#L111-L114
					// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Controller/QuizAnswersController.php#L286
					$nc3Quiz = $Quiz->findById($quizMap['Quiz']['id'], null, null, 1);
					$questionMap = $this->getQuestionMap($nc2QSummary, $nc3Quiz);

					$nc2PreviousQId = $nc2CurrentQId;
				}
				if (!$questionMap) {
					$QAnswerSummary->rollback();
					continue;
				}

				if (!$this->__saveQuizAnswerFromNc2($nc2QSummary, $data, $nc3Quiz, $questionMap)) {
					$QAnswerSummary->rollback();
					continue;
				}

				$QAnswerSummary->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuizFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$QAnswerSummary->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuizAnswerSummary data Migration end.'));

		return true;
	}

/**
 * Save QuizAnswerSummary from Nc2.
 *
 * @param array $nc2QSummary Nc2QuizSummary data.
 * @param array $nc3QAnswerSummary Nc3QuizAnswerSummary data.
 * @param array $nc3Quiz Nc3Quiz data.
 * @param array $questionMap QuizQuestion map data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuizAnswerFromNc2($nc2QSummary, $nc3QAnswerSummary, $nc3Quiz, $questionMap) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '    QuizAnswer data Migration start.'));

		/* @var $Nc2QAnswer AppModel */
		$Nc2QAnswer = $this->getNc2Model('quiz_answer');
		$nc2QSummaryId = $nc2QSummary['Nc2QuizSummary']['summary_id'];
		$nc2QAnswers = $Nc2QAnswer->findAllBySummaryId($nc2QSummaryId, null, null, null, null, -1);

		/* @var $QuizAnswer QuizAnswer */
		$QuizAnswer = ClassRegistry::init('Quizzes.QuizAnswer');
		// Nc2ToNc3Quiz::__saveQuizAnswerSummaryFromNc2 で発行済み
		//$QuizAnswer->begin();
		try {
			$data = $this->generateNc3QuizAnswerData($nc2QAnswers, $questionMap);
			if (!$data) {
				//$QuizAnswer->rollback();
				return false;
			}

			if (!$QuizAnswer->saveAnswer($data, $nc3Quiz, $nc3QAnswerSummary)) {
				// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
				// var_exportは大丈夫らしい。。。
				// @see https://phpmd.org/rules/design.html
				$message = $this->getLogArgument($nc2QSummary) . "\n" .
					var_export($QuizAnswer->validationErrors, true);
				$this->writeMigrationLog($message);

				//$QuizAnswer->rollback();
				return false;
			}

			//$QuizAnswer->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $QuizFrameSetting::savePage()でthrowされるとこの処理に入ってこない
			//$QuizAnswer->rollback($ex);
			throw $ex;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '    QuizAnswer data Migration end.'));

		return true;
	}

/**
 * Set Current data.
 *
 * @param array $nc2QBlock Nc2QuizBlock data.
 * @return bool True on success
 */
	private function __setCurrentData($nc2QBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');

		// QuizFrameDisplayQuiz::saveDisplayQuiz でFrameに割り当てられてしまうが、
		// Nc2ToNc3Quiz::__saveQuizFrameSettingFromNc2で再登録を行うことで調整
		// Frameデータ移行時にQuiz::afterFrameSaveでFrame.block_idが割り振られるはずだが、何かでNULLのまま。→要調査（とりあえず次へ進んどく）
		// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/Quiz.php#L577-L578
		// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/Quiz.php#L631-L634
		$frameMap = $Nc2ToNc3Frame->getMap($nc2QBlock['Nc2QuizBlock']['block_id']);
		if (!isset($frameMap['Frame']['block_id'])) {
			return false;
		}

		$nc3RoomId = $frameMap['Frame']['room_id'];
		Current::write('Frame.key', $frameMap['Frame']['key']);
		Current::write('Frame.room_id', $nc3RoomId);
		Current::write('Frame.plugin_key', 'quizzes');
		Current::write('Frame.block_id', $frameMap['Frame']['block_id']);

		// QuizFrameDisplayQuiz::validates に引っかかる。is_ativeも条件になり、一時保存データが取得できないので、content_editableもtrue
		// Questionnaireはvalidateしてない。いいのか？
		// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizFrameDisplayQuiz.php#L69-L90
		// @see https://github.com/NetCommons3/Quizzes/blob/3.1.0/Model/QuizFrameDisplayQuiz.php#L257
		// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireFrameDisplayQuestionnaire.php#L259
		Current::write('Block.id', $frameMap['Frame']['block_id']);
		CurrentBase::$permission[$nc3RoomId]['Permission']['content_editable']['value'] = true;

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
		Current::write('Plugin.key', 'quizzes');

		// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
		Current::write('Room.id', $nc3RoomId);
		CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

		return true;
	}

/**
 * unset Current data.
 *
 * @return void
 */
	private function __unSetCurrentData() {
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Frame.block_id');
		Current::remove('Frame.Block.id');
		Current::remove('Plugin.key');
		Current::remove('Room.id');

		// Fatal error: Attempt to unset static property が発生。keyを指定した場合は発生しない。なんで？
		//unset(CurrentBase::$permission);
		$nc3RoomIds = array_keys(CurrentBase::$permission);
		foreach ($nc3RoomIds as $nc3RoomId) {
			unset(CurrentBase::$permission[$nc3RoomId]);
		}
	}
}
