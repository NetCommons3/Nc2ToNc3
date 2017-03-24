<?php
/**
 * Nc2ToNc3Questionnaire
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Questionnaire
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
 * @see Nc2ToNc3QuestionnaireBehavior
 * @method string getLogArgument($nc2Questionnaire)
 * @method array generateNc3QuestionnaireData($nc2Questionnaire)
 * @method array generateNc3QuestionnaireFrameSettingData($nc2QBlock)
 * @method array generateNc3QuestionnaireAnswerSummaryData($nc2QSummary)
 * @method array generateNc3QuestionnaireAnswerData($nc2QAnswers)
 * @method array getQuestionMap($nc2QuestionnaireId, $nc3QuestionnaireKey)
 *
 */
class Nc2ToNc3Questionnaire extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Questionnaire'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Questionnaire Migration start.'));

		/* @var $Nc2Questionnaire AppModel */
		$Nc2Questionnaire = $this->getNc2Model('questionnaire');
		$nc2Questionnaires = $Nc2Questionnaire->find('all');
		if (!$this->__saveQuestionnaireFromNc2($nc2Questionnaires)) {
			return false;
		}

		/* @var $Nc2QBlock AppModel */
		$Nc2QBlock = $this->getNc2Model('questionnaire_block');
		$nc2QBlocks = $Nc2QBlock->find('all');
		if (!$this->__saveQuestionnaireFrameSettingFromNc2($nc2QBlocks)) {
			return false;
		}

		/* @var $Nc2QSummary AppModel */
		$Nc2QSummary = $this->getNc2Model('questionnaire_summary');
		$query = [
			'order' => [
				'questionnaire_id',
				'answer_number',
			],
			'recursive' => -1,
		];
		$nc2QSummaries = $Nc2QSummary->find('all', $query);
		if (!$this->__saveQuestionnaireAnswerSummaryFromNc2($nc2QSummaries)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Questionnaire Migration end.'));
		return true;
	}

/**
 * Save Questionnaire from Nc2.
 *
 * @param array $nc2Questionnaires Nc2Questionnaire data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuestionnaireFromNc2($nc2Questionnaires) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Questionnaire data Migration start.'));

		/* @var $Questionnaire Questionnaire */
		/* @var $Nc2Questionnaire AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		$Questionnaire = ClassRegistry::init('Questionnaires.Questionnaire');
		$Nc2QBlock = $this->getNc2Model('questionnaire_block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2Questionnaires as $nc2Questionnaire) {
			$Questionnaire->begin();
			try {
				$data = $this->generateNc3QuestionnaireData($nc2Questionnaire);
				if (!$data) {
					$Questionnaire->rollback();
					continue;
				}

				$nc2RoomId = $nc2Questionnaire['Nc2Questionnaire']['room_id'];
				$nc2QBlock = $Nc2QBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2QBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Questionnaire));
					$this->writeMigrationLog($message);
					$Questionnaire->rollback();
					continue;
				}
				// QuestionnaireFrameDisplayQuestionnaire::saveDisplayQuestionnaire でFrameに割り当てられてしまうが、
				// Nc2ToNc3Questionnaire::__saveQuestionnaireFrameSettingFromNc2で再登録を行うことで調整
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L577-L578
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L631-L634
				$frameMap = $Nc2ToNc3Frame->getMap($nc2QBlock['Nc2QuestionnaireBlock']['block_id']);
				$nc3RoomId = $frameMap['Frame']['room_id'];
				Current::write('Frame.key', $frameMap['Frame']['key']);
				Current::write('Frame.room_id', $nc3RoomId);
				Current::write('Frame.plugin_key', 'questionnaires');

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
				Current::write('Plugin.key', 'questionnaires');

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// Model::idを初期化しないとUpdateになってしまう。
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L442
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireSetting.php#L129-L149
				$Frame->create();

				if (!$Questionnaire->saveQuestionnaire($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Questionnaire->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Questionnaire) . "\n" .
						var_export($Questionnaire->validationErrors, true);
					$this->writeMigrationLog($message);

					$Questionnaire->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2QuestionnaireId = $nc2Questionnaire['Nc2Questionnaire']['questionnaire_id'];
				$idMap = [
					$nc2QuestionnaireId => $Questionnaire->id
				];
				$this->saveMap('Questionnaire', $idMap);

				$Questionnaire->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Questionnaire->rollback($ex);
				throw $ex;
			}
		}

		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Plugin.key');
		Current::remove('Room.id');
		// Fatal error: Attempt to unset static property が発生。keyを指定した場合は発生しない。なんで？
		//unset(CurrentBase::$permission);

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Questionnaire data Migration end.'));

		return true;
	}

/**
 * Save QuestionnaireFrameSetting from Nc2.
 *
 * @param array $nc2QBlocks Nc2QuestionnaireBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuestionnaireFrameSettingFromNc2($nc2QBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuestionnaireFrameSetting data Migration start.'));

		/* @var $QFrameSetting QuestionnaireFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Block Block */
		$QFrameSetting = ClassRegistry::init('Questionnaires.QuestionnaireFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		foreach ($nc2QBlocks as $nc2QBlock) {
			$QFrameSetting->begin();
			try {
				$data = $this->generateNc3QuestionnaireFrameSettingData($nc2QBlock);
				if (!$data) {
					$QFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2QBlock['Nc2QuestionnaireBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2QBlock));
					$this->writeMigrationLog($message);
					$QFrameSetting->rollback();
					continue;
				}
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireFrameDisplayQuestionnaire.php#L221
				Current::write('Frame.key', $frameMap['Frame']['key']);

				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireFrameSetting.php#L165-L167
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/Questionnaire.php#L464
				$nc3Block = $Block->findByRoomIdAndPluginKey(
					$frameMap['Frame']['room_id'],
					'questionnaires',
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
				$this->saveMap('QuestionnaireFrameSetting', $idMap);

				$QFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$QFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuestionnaireFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save QuestionnaireAnswerSummary from Nc2.
 *
 * @param array $nc2QSummaries Nc2QuestionnaireSummary data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuestionnaireAnswerSummaryFromNc2($nc2QSummaries) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuestionnaireAnswerSummary data Migration start.'));

		/* @var $QAnswerSummary QuestionnaireAnswerSummary */
		/* @var $Questionnaire Questionnaire */
		$QAnswerSummary = ClassRegistry::init('Questionnaires.QuestionnaireAnswerSummary');
		$Questionnaire = ClassRegistry::init('Questionnaires.Questionnaire');
		$nc2PreviousQId = null;
		foreach ($nc2QSummaries as $nc2QSummary) {
			$QAnswerSummary->begin();
			try {
				$data = $this->generateNc3QuestionnaireAnswerSummaryData($nc2QSummary);
				if (!$data) {
					$QAnswerSummary->rollback();
					continue;
				}

				// Model::idを初期化しないと$QAnswerSummary::data[id]がセットされず、MailQueueBehaviorでNotice Errorになってしまう。
				// @see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/Model.php#L1962-L1964
				// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Model/QuestionnaireAnswerSummary.php#L35
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

				$nc2QSummaryId = $nc2QSummary['Nc2QuestionnaireSummary']['summary_id'];
				$idMap = [
					$nc2QSummaryId => $QAnswerSummary->id
				];
				$this->saveMap('QuestionnaireAnswerSummary', $idMap);

				// Nc2QuestionnaireSummary.questionnaire_id 毎に
				// 対応するQuestionnaireQuestion,QuestionnaireChoiceをまとめて取得する
				$nc2CurrentQId = $nc2QSummary['Nc2QuestionnaireSummary']['questionnaire_id'];
				if ($nc2CurrentQId != $nc2PreviousQId) {

					$questionnaireMap = $this->getMap($nc2CurrentQId);
					// 移行後に修正されることを考慮し、対応するidでQuestionnaireデータを取得
					// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Controller/QuestionnaireAnswersController.php#L111-L114
					// @see https://github.com/NetCommons3/Questionnaires/blob/3.1.0/Controller/QuestionnaireAnswersController.php#L286
					$nc3Questionnaire = $Questionnaire->findById($questionnaireMap['Questionnaire']['id'], null, null, 1);
					$questionMap = $this->getQuestionMap($nc2QSummary, $nc3Questionnaire);

					$nc2PreviousQId = $nc2CurrentQId;
				}
				if (!$questionMap) {
					$QAnswerSummary->rollback();
					continue;
				}

				if (!$this->__saveQuestionnaireAnswerFromNc2($nc2QSummary, $data, $nc3Questionnaire, $questionMap)) {
					$QAnswerSummary->rollback();
					continue;
				}

				$QAnswerSummary->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$QAnswerSummary->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  QuestionnaireAnswerSummary data Migration end.'));

		return true;
	}

/**
 * Save QuestionnaireAnswerSummary from Nc2.
 *
 * @param array $nc2QSummary Nc2QuestionnaireSummary data.
 * @param array $nc3QAnswerSummary Nc3QuestionnaireAnswerSummary data.
 * @param array $nc3Questionnaire Nc3Questionnaire data.
 * @param array $questionMap QuestionnaireQuestion map data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveQuestionnaireAnswerFromNc2($nc2QSummary, $nc3QAnswerSummary, $nc3Questionnaire, $questionMap) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '    QuestionnaireAnswer data Migration start.'));

		/* @var $Nc2QAnswer AppModel */
		$Nc2QAnswer = $this->getNc2Model('questionnaire_answer');
		$nc2QSummaryId = $nc2QSummary['Nc2QuestionnaireSummary']['summary_id'];
		$nc2QAnswers = $Nc2QAnswer->findAllBySummaryId($nc2QSummaryId, null, null, null, null, -1);

		/* @var $QuestionnaireAnswer QuestionnaireAnswer */
		$QuestionnaireAnswer = ClassRegistry::init('Questionnaires.QuestionnaireAnswer');
		// Nc2ToNc3Questionnaire::__saveQuestionnaireAnswerSummaryFromNc2 で発行済み
		//$QuestionnaireAnswer->begin();
		try {
			$data = $this->generateNc3QuestionnaireAnswerData($nc2QAnswers, $questionMap);
			if (!$data) {
				//$QuestionnaireAnswer->rollback();
				return false;
			}

			if (!$QuestionnaireAnswer->saveAnswer($data, $nc3Questionnaire, $nc3QAnswerSummary)) {
				// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
				// var_exportは大丈夫らしい。。。
				// @see https://phpmd.org/rules/design.html
				$message = $this->getLogArgument($nc2QSummary) . "\n" .
					var_export($QuestionnaireAnswer->validationErrors, true);
				$this->writeMigrationLog($message);

				//$QuestionnaireAnswer->rollback();
				return false;
			}

			//$QuestionnaireAnswer->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
			//$QuestionnaireAnswer->rollback($ex);
			throw $ex;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '    QuestionnaireAnswer data Migration end.'));

		return true;
	}

}
