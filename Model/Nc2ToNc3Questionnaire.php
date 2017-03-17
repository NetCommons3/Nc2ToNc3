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
		$Questionnaire = ClassRegistry::init('Questionnaires.Questionnaire');
		$Nc2QBlock = $this->getNc2Model('questionnaire_block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
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
				// Nc2ToNc3Questionnaire::saveQuestionnaireFrameDisplayFromNc2で再登録を行うことで調整
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

				if (!$Questionnaire->saveQuestionnaire($data)) {
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

}
