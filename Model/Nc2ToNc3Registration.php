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

		$Nc2RegistrationBlock = $this->getNc2Model('registration_block');
		$nc2RBlocks = $Nc2RegistrationBlock->find('all');
		if (!$this->__saveFrameFromNc2($nc2RBlocks)) {
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
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		/* @var $Block Block */
		$Registration = ClassRegistry::init('Registrations.Registration');
		$Registration->MailSetting = ClassRegistry::init('Mails.MailSetting');
		$Registration->MailSettingFixedPhrase = ClassRegistry::init('Mails.MailSettingFixedPhrase');
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
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2RBlock));
					$this->writeMigrationLog($message);
					$Registration->rollback();
					continue;
				}
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

				// 本来 Registrationの独自ビヘイビアMailSettingBehaviorでcreate()した方がよい。
				// とはいえ、ループで$Registration->saveRegistration()を繰り返し呼び出すのは、移行ツール位。
				// 移行ツールだけの対応でもメール設定が移行できるようにするため、ここでcreate()する
				$Registration->MailSetting->create();
				$Registration->MailSettingFixedPhrase->create();

				if (!$Registration->saveRegistration($data)) {
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
		/* @var $Nc2RegistrationItemData AppModel */
		$Registration = ClassRegistry::init('Registrations.Registration');
		$RAnswerSummary = ClassRegistry::init('Registrations.RegistrationAnswerSummary');
		$NcRItemData = $this->getNc2Model('registration_item_data');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Registration');
		foreach ($nc2RegistrationData as $nc2RegistrationDatum) {
			$RAnswerSummary->begin();
			try {

				$nc2RegistrationId = $nc2RegistrationDatum['Nc2RegistrationData']['registration_id'];
				$nc2DataId = $nc2RegistrationDatum['Nc2RegistrationData']['data_id'];
				$nc2ItemData = $NcRItemData->findAllByDataId($nc2DataId, null, null, -1);

				// NC2のRegistrationIdから、NC3のRegistrationId取得. --> $mapIdList[$nc2RegistrationId]
				if (!isset($mapIdList[$nc2RegistrationId])) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2RegistrationDatum));
					$this->writeMigrationLog($message);

					$RAnswerSummary->rollback();
					continue;
				}
				$nc3Registration = $Registration->findById($mapIdList[$nc2RegistrationId]);

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

/**
 * Save Frame from Nc2.
 *
 * @param array $nc2RBlocks Nc2RegistrationBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveFrameFromNc2($nc2RBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Frame data Migration start.'));

		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Registration Registration */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Registration = ClassRegistry::init('Registrations.Registration');
		foreach ($nc2RBlocks as $nc2RegistrationBlock) {
			$Frame->begin();
			try {
				$nc2BlockId = $nc2RegistrationBlock['Nc2RegistrationBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2RegistrationBlock));
					$this->writeMigrationLog($message);

					$Frame->rollback();
					continue;
				}

				// RegistrationFrameModelは存在しないが、移行済みデータのためにDummyModel名で取得
				$mapIdList = $Nc2ToNc3Map->getMapIdList('RegistrationFrame', $nc2BlockId);
				if ($mapIdList) {
					$Frame->rollback();	// 移行済み
					continue;
				}

				$nc2RegistrationId = $nc2RegistrationBlock['Nc2RegistrationBlock']['registration_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Registration', $nc2RegistrationId);
				$nc3RegistrationId = Hash::get($mapIdList, [$nc2RegistrationId]);
				$nc3Registration = $Registration->findById($nc3RegistrationId, ['block_id'], null, -1);
				if (!$nc3Registration) {
					$Frame->rollback();	// ブロックデータなし
					continue;
				}

				$data['Frame'] = [
					'id' => $frameMap['Frame']['id'],
					'plugin_key' => 'registrations',
					'block_id' => $nc3Registration['Registration']['block_id'],
					'default_action' => 'registration_answers/view/',
				];
				// 前処理のFrame::data が残っている場合があるので、上書しとく。
				$Frame->read(array_keys($data['Frame']), $frameMap['Frame']['id']);
				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。 var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2RBlocks) . "\n" .
						var_export($Frame->validationErrors, true);
						$this->writeMigrationLog($message);

						$Frame->rollback();
						continue;
				}

				$idMap = [
					$nc2BlockId => $frameMap['Frame']['id'],
				];
				// RegistrationFrameModelは存在しないが、移行済みデータのためにDummyModel名で登録
				$this->saveMap('RegistrationFrame', $idMap);

				$Frame->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Frame->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Frame data Migration end.'));

		return true;
	}

}
