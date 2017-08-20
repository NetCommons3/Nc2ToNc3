<?php
/**
 * Nc2ToNc3Registration
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Registration
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
 * @see Nc2ToNc3RegistrationBehavior
 * @method string getLogArgument($nc2Registration)
 * @method array generateNc3RegistrationData($nc2Registration)
 * @method array generateNc3RegistrationAnswerSummaryData($nc2ItemData, $nc3Registration)
 * @method array generateNc3RegistrationAnswerData($nc2ItemData, $questionMap)
 * @method array getQuestionMap($nc2RegistrationId, $nc3RegistrationKey)
 *
 */
class Nc2ToNc3Registration extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Registration'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Registration Migration start.'));

		/* @var $Nc2Registration AppModel */
		$Nc2Registration = $this->getNc2Model('registration');
		$nc2Registrations = $Nc2Registration->find('all');
		if (!$this->__saveRegistrationFromNc2($nc2Registrations)) {
			return false;
		}

		/* @var $Nc2RegistrationData AppModel */
		$Nc2RegistrationData = $this->getNc2Model('registration_data');
		$nc2RegistrationData = $Nc2RegistrationData->find('all');
		if (!$this->__saveRegistrationDataFromNc2($nc2RegistrationData)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Registration Migration end.'));

		return true;
	}

/**
 * Save Registration from Nc2.
 *
 * @param array $nc2Registrations Nc2Registration data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveRegistrationFromNc2($nc2Registrations) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Registration data Migration start.'));

		/* @var $Registration Registration */
		/* @var $Nc2RegistrationBlock AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		/* @var $Block Block */
		$Registration = ClassRegistry::init('Registrations.Registration');
		$NcRBlock = $this->getNc2Model('registration_block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		$Block = ClassRegistry::init('Blocks.Block');
		foreach ($nc2Registrations as $nc2Registration) {
			$Registration->begin();
			try {
				$nc2RoomId = $nc2Registration['Nc2Registration']['room_id'];
				$nc2RBlock = $NcRBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2RBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Registration));
					$this->writeMigrationLog($message);
					$Registration->rollback();
					continue;
				}
				$frameMap = $Nc2ToNc3Frame->getMap($nc2RBlock['Nc2RegistrationBlock']['block_id']);
				$frame = $Frame->findById($frameMap['Frame']['id'], null, null, -1);
				$nc3RoomId = $frameMap['Frame']['room_id'];
				Current::write('Frame', $frame['Frame']);
				Current::write('Room.id', $nc3RoomId);
				$Frame->create();
				$Block->create();
				$Registration->createBlock($frame);

				$data = $this->generateNc3RegistrationData($nc2Registration);
				if (!$data) {
					$Registration->rollback();
					continue;
				}

				$this->writeCurrent($frameMap, 'registrations');

				if (!$Registration->saveRegistration($data)) {
					ini_set('xdebug.var_display_max_children', -1);
					ini_set('xdebug.var_display_max_data', -1);
					ini_set('xdebug.var_display_max_depth', -1);

					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Registration->rollback();

					$message = $this->getLogArgument($nc2Registration) . "\n" .
						var_export($Registration->validationErrors, true);
					$this->writeMigrationLog($message);

					$Registration->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2RegistrationId = $nc2Registration['Nc2Registration']['registration_id'];
				$idMap = [
					$nc2RegistrationId => $Registration->id,
				];
				$this->saveMap('Registration', $idMap);

				$Registration->commit();

			} catch (Exception $ex) {
				$Registration->rollback($ex);
				throw $ex;
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Registration data Migration end.'));

		return true;
	}

/**
 * Save RegistrationData from Nc2.
 *
 * @param array $nc2RegistrationData Nc2RegistrationData data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveRegistrationDataFromNc2($nc2RegistrationData) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  RegistrationData data Migration start.'));

		/* @var $Registration Registration */
		/* @var $Registration RegistrationAnswerSummary */
		/* @var $Nc2RegistrationItemData AppModel */
		$Registration = ClassRegistry::init('Registrations.Registration');
		$RAnswerSummary = ClassRegistry::init('Registrations.RegistrationAnswerSummary');
		$NcRItemData = $this->getNc2Model('registration_item_data');
		foreach ($nc2RegistrationData as $nc2RegistrationDatum) {
			$RAnswerSummary->begin();
			try {

				$nc2RegistrationId = $nc2RegistrationDatum['Nc2RegistrationData']['registration_id'];
				$nc2DataId = $nc2RegistrationDatum['Nc2RegistrationData']['data_id'];
				$nc2ItemData = $NcRItemData->findAllByDataId($nc2DataId, null, null, -1);
				$nc3Registration = $Registration->findById($nc2RegistrationId);

				$nc2ItemDataFirst = reset($nc2ItemData);
				$data = $this->generateNc3RegistrationAnswerSummaryData($nc2ItemDataFirst, $nc3Registration);
				if (!$data) {
					$RAnswerSummary->rollback();
					continue;
				}

				$RAnswerSummary->create();

				if (!($nc3Summary = $RAnswerSummary->save($data))) {
					$message = $this->getLogArgument($nc2ItemData) . "\n" .
						var_export($RAnswerSummary->validationErrors, true);
					$this->writeMigrationLog($message);

					$RAnswerSummary->rollback();
					continue;
				}

				$nc2DataId = $nc2RegistrationDatum['Nc2RegistrationData']['data_id'];
				$idMap = [
					$nc2DataId => $RAnswerSummary->id,
				];
				$this->saveMap('RegistrationAnswerSummary', $idMap);

				if (!$this->__saveRegistrationAnswerFromNc2($nc2ItemData, $nc3Registration, $nc3Summary)) {
					$RAnswerSummary->rollback();
					continue;
				}

				$RAnswerSummary->commit();

			} catch (Exception $ex) {
				$RAnswerSummary->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  RegistrationAnswerSummary data Migration end.'));

		return true;
	}

/**
 * Save RegistrationAnswer from Nc2.
 *
 * @param array $nc2ItemData Nc2RegistrationItemData data.
 * @param array $nc3Registration Nc3Registration data.
 * @param array $nc3Summary RegistrationAnswerSummary map data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveRegistrationAnswerFromNc2($nc2ItemData, $nc3Registration, $nc3Summary) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '    RegistrationAnswer data Migration start.'));

		/* @var $RegistrationAnswer RegistrationAnswer */
		$RegistrationAnswer = ClassRegistry::init('Registrations.RegistrationAnswer');
		//$RegistrationAnswer->begin();
			try {
			$nc2ItemDataFirst = reset($nc2ItemData);
			$questionMap = $this->getQuestionMap($nc2ItemDataFirst, $nc3Registration);

			$data = $this->generateNc3RegistrationAnswerData($nc2ItemData, $questionMap);
			if (!$data) {
				//$RegistrationAnswer->rollback();
				return false;
			}
			if (!$RegistrationAnswer->saveAnswer($data, $nc3Registration, $nc3Summary)) {
				$message = $this->getLogArgument($nc2ItemDataFirst) . "\n" .
					var_export($RegistrationAnswer->validationErrors, true);
				$this->writeMigrationLog($message);
				$RegistrationAnswer->rollback();
				return false;
			}
			//$RegistrationAnswer->commit();
			} catch (Exception $ex) {
			//$RegistrationAnswer->rollback();
			throw $ex;
			}

		$this->writeMigrationLog(__d('nc2_to_nc3', '    RegistrationAnswer data Migration end.'));

		return true;
	}
}
