<?php
/**
 * Nc2ToNc3RegistrationBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3QuestionBaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3RegistrationBehavior
 *
 */
class Nc2ToNc3RegistrationBehavior extends Nc2ToNc3QuestionBaseBehavior {

/**
 * Answer count per user
 *
 * @var array
 */
	private $__answerCountPerUser = [];

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Registration Array data of Nc2Registration.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Registration) {
		return $this->__getLogArgument($nc2Registration);
	}

/**
 * Generate Nc3Registration data.
 *
 * Data sample
 * data[Registration][key]:
 * data[Registration][is_active]:
 * data[Registration][status]:
 * data[Registration][title]:
 * data[Registration][title_icon]:
 * data[Registration][is_total_show]:
 * data[Registration][answer_timing]:
 * data[Registration][is_key_pass_use]:
 * data[Registration][total_show_timing]:
 * data[Registration][registration_mail_subject]:
 * data[Registration][registration_mail_body]:
 * data[Registration][sub_title]:
 * data[Registration][is_answer_mail_send]:0
 * data[Registration][is_image_authentication]:0
 * data[Registration][reply_to]:
 * data[Registration][answer_start_period]:
 * data[Registration][answer_end_period]:
 * data[Registration][is_limit_number]:0
 * data[Registration][limit_number]:
 * data[Registration][created_user]:0
 * data[Registration][created]:0
 * data[RegistrationPage][0][page_sequence]:0
 * data[RegistrationPage][0][RegistrationQuestion][0][key]:
 * data[RegistrationPage][0][RegistrationQuestion][0][question_sequence]:0
 * data[RegistrationPage][0][RegistrationQuestion][0][question_value]:新規質問1
 * data[RegistrationPage][0][RegistrationQuestion][0][question_type]:1
 * data[RegistrationPage][0][RegistrationQuestion][0][description]:
 * data[RegistrationPage][0][RegistrationQuestion][0][is_require]:0
 * data[RegistrationPage][0][RegistrationQuestion][0][question_type_option]:
 * data[RegistrationPage][0][RegistrationQuestion][0][is_result_display]:0
 * data[RegistrationPage][0][RegistrationQuestion][0][result_display_type]:0
 * data[RegistrationPage][0][RegistrationQuestion][0][RegistrationChoice][0][key]:19d7cb6c3045c3c54415446e2a3c71ae
 * data[RegistrationPage][0][RegistrationQuestion][0][RegistrationChoice][0][choice_sequence]:0
 * data[RegistrationPage][0][RegistrationQuestion][0][RegistrationChoice][0][choice_label]:新規選択肢1
 * data[RegistrationPage][0][RegistrationQuestion][0][RegistrationChoice][0][graph_color]:
 * data[Topic][plugin_key]:registrations
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Registration Nc2Registration data.
 * @return array Nc3Registration data.
 */
	public function generateNc3RegistrationData(Model $model, $nc2Registration) {
		$nc2RegistrationId = $nc2Registration['Nc2Registration']['registration_id'];
		$registrationMap = $this->_getMap($nc2RegistrationId);
		if ($registrationMap) {
			// 既存の場合
			return [];
		}

		$answerTiming = '0';
		$endPeriod = '';
		if ($nc2Registration['Nc2Registration']['period'] !== '') {
			$answerTiming = '1';
			$endPeriod = $this->_convertDate($nc2Registration['Nc2Registration']['period']);
		}

		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$roomMap = $Nc2ToNc3Room->getMap($nc2Registration['Nc2Registration']['room_id']);
		if (!$roomMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2Registration));
			$this->_writeMigrationLog($message);
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc3CreatedUser = $Nc2ToNc3User->getCreatedUser($nc2Registration['Nc2Registration']);
		$nc3Created = $this->_convertDate($nc2Registration['Nc2Registration']['insert_time']);
		$data = [
			'Registration' => [
				'key' => Hash::get($registrationMap, ['Registration', 'key']),
				'is_active' => '1',
				'status' => '1',
				'title' => $nc2Registration['Nc2Registration']['registration_name'],
				//'title_icon' => $this->_convertTitleIcon($nc2Registration['Nc2Registration']['title_icon']),
				'is_total_show' => '0',
				'answer_timing' => $answerTiming,
				'is_key_pass_use' => RegistrationsComponent::USES_NOT_USE,
				'total_show_timing' => '0',
				'registration_mail_subject' => $nc2Registration['Nc2Registration']['mail_subject'],
				'registration_mail_body' => $nc2Registration['Nc2Registration']['mail_body'],
				'sub_title' => '',
				'is_answer_mail_send' => $nc2Registration['Nc2Registration']['mail_send'],
				'is_image_authentication' => $nc2Registration['Nc2Registration']['image_authentication'],
				'reply_to' => $nc2Registration['Nc2Registration']['rcpt_to'],
				'answer_start_period' => '',
				'answer_end_period' => $endPeriod,
				'is_limit_number' => (empty($nc2Registration['Nc2Registration']['limit_number']) ? '0' : '1'),
				'limit_number' => $nc2Registration['Nc2Registration']['limit_number'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
			'Block' => [
				'id' => '',
				'room_id' => $roomMap['Room']['id'],
				'plugin_key' => 'registrations',
				'name' => $nc2Registration['Nc2Registration']['registration_name'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
			'BlocksLanguage' => [
				'language_id' => '',
				'name' => $nc2Registration['Nc2Registration']['registration_name'],
				'created_user' => $nc3CreatedUser,
				'created' => $nc3Created,
			],
		];

		if ($nc2Registration['Nc2Registration']['image_authentication'] == '1') {
			$data['Registration']['is_image_authentication'] = '0';
		}

		$data['RegistrationPage'] = $this->__generateNc3RegistrationPageData($nc2Registration);

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
		$data['Topic'] = [
			'plugin_key' => 'registrations',
		];

		return $data;
	}

/**
 * Generate Nc3RegistrationAnswerSummary data.
 *
 * Data sample
 * data[RegistrationAnswerSummary][answer_status]:2
 * data[RegistrationAnswerSummary][test_status]:
 * data[RegistrationAnswerSummary][answer_number]:
 * data[RegistrationAnswerSummary][answer_time]:
 * data[RegistrationAnswerSummary][registration_key]:
 * data[RegistrationAnswerSummary][session_value]:
 * data[RegistrationAnswerSummary][user_id]:
 * data[RegistrationAnswerSummary][created_user]:
 * data[RegistrationAnswerSummary][created]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2ItemDataFirst Nc2RegistrationItemData data.
 * @param array $nc3Registration Registration data.
 * @return array Nc3RegistrationAnswerSummary data.
 */
	public function generateNc3RegistrationAnswerSummaryData(Model $model, $nc2ItemDataFirst, $nc3Registration) {
		$nc2RegistrationId = $nc2ItemDataFirst['Nc2RegistrationItemData']['registration_id'];
		$registrationMap = $this->_getMap($nc2RegistrationId);
		if (!$registrationMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2ItemDataFirst));
			$this->_writeMigrationLog($message);

			return [];
		}

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc2DataId = $nc2ItemDataFirst['Nc2RegistrationItemData']['data_id'];
		$mapIdList = $Nc2ToNc3Map->getMapIdList('RegistrationAnswerSummary', $nc2DataId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		$nc3AnswerNumber = 1;
		$nc3UserId = null;
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		if ($nc2ItemDataFirst['Nc2RegistrationItemData']['insert_user_id'] != '0') {
			$nc3UserId = $Nc2ToNc3User->getCreatedUser($nc2ItemDataFirst['Nc2RegistrationItemData']);
			if (!isset($this->__answerCountPerUser[$nc2RegistrationId][$nc3UserId])) {
				$this->__answerCountPerUser[$nc2RegistrationId][$nc3UserId] = $nc3AnswerNumber;
			} else {
				$nc3AnswerNumber = ++$this->__answerCountPerUser[$nc2RegistrationId][$nc3UserId];
			}
		}

		$data['RegistrationAnswerSummary'] = [
			'answer_status' => '2',
			'test_status' => '0',
			'answer_number' => $nc3AnswerNumber,
			'answer_time' => $this->_convertDate($nc2ItemDataFirst['Nc2RegistrationItemData']['insert_time']),
			'registration_key' => $nc3Registration['Registration']['key'],
			'session_value' => '',
			'user_id' => $nc3UserId,
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2ItemDataFirst['Nc2RegistrationItemData']),
			'created' => $this->_convertDate($nc2ItemDataFirst['Nc2RegistrationItemData']['insert_time']),
			'modified_user' => $Nc2ToNc3User->getCreatedUser($nc2ItemDataFirst['Nc2RegistrationItemData']),
			'modified' => $this->_convertDate($nc2ItemDataFirst['Nc2RegistrationItemData']['update_time']),
		];

		return $data;
	}

/**
 * Generate Nc3RegistrationAnswer data.
 *
 * Data sample
 * data[RegistrationAnswer][df6486636e1dba5fc7a8c2ce3114ecf1][0][answer_value]:|657ff45a8529909b88937f38a3371b7d:選択肢B
 * data[RegistrationAnswer][df6486636e1dba5fc7a8c2ce3114ecf1][0][registration_question_key]:df6486636e1dba5fc7a8c2ce3114ecf1
 * data[RegistrationAnswer][df6486636e1dba5fc7a8c2ce3114ecf1][0][matrix_choice_key]:
 * data[RegistrationAnswer][517e6c02f3e084f1ff8eacd82cf4344b][0][answer_value][]:|0449ef68f919976a0d4932929a4fa812:sequenceA
 * data[RegistrationAnswer][517e6c02f3e084f1ff8eacd82cf4344b][0][answer_value][]:|53779efe8a97ab064f7a56fe129fb500:sequenceB
 * data[RegistrationAnswer][517e6c02f3e084f1ff8eacd82cf4344b][0][registration_question_key]:517e6c02f3e084f1ff8eacd82cf4344b
 * data[RegistrationAnswer][7189ba2ed5f9ff9ba82fbf194f91daa9][0][answer_value]:text value
 * data[RegistrationAnswer][7189ba2ed5f9ff9ba82fbf194f91daa9][0][registration_question_key]:7189ba2ed5f9ff9ba82fbf194f91daa9
 * data[RegistrationAnswer][7189ba2ed5f9ff9ba82fbf194f91daa9][0][matrix_choice_key]:
 * data[RegistrationAnswer][447a6a338466dba0b3855a247473ba53][0][answer_value]:mail value
 * data[RegistrationAnswer][447a6a338466dba0b3855a247473ba53][0][answer_value_again]:mail value
 * data[RegistrationAnswer][447a6a338466dba0b3855a247473ba53][0][registration_question_key]:447a6a338466dba0b3855a247473ba53
 * data[RegistrationAnswer][447a6a338466dba0b3855a247473ba53][0][matrix_choice_key]:
 * data[RegistrationAnswer][1e8c4984041da363dee126e84f3b4006][0][answer_value_file]:file value
 * data[RegistrationAnswer][1e8c4984041da363dee126e84f3b4006][0][registration_question_key]:1e8c4984041da363dee126e84f3b4006
 * data[RegistrationAnswer][1e8c4984041da363dee126e84f3b4006][0][matrix_choice_key]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2ItemData Nc2Item data.
 * @param array $registrationMap Registration map data.
 * @return array Nc3RegistrationAnswer data.
 */
	public function generateNc3RegistrationAnswerData(Model $model, $nc2ItemData, $registrationMap) {
		$data = [];
		foreach ($nc2ItemData as $nc2Item) {
			$nc2ItemId = $nc2Item['Nc2RegistrationItemData']['item_id'];
			$nc3QuestionKey = $registrationMap[$nc2ItemId]['RegistrationQuestion']['key'];

			$nc2AnswerValue = $nc2Item['Nc2RegistrationItemData']['item_data_value'];
			if ($registrationMap[$nc2ItemId]['RegistrationQuestion']['question_type'] === '9') {
				// メールの場合
				$answerArr = [
					'answer_value' => $nc2AnswerValue,
					'answer_value_again' => $nc2AnswerValue,
				];
			} elseif ($registrationMap[$nc2ItemId]['RegistrationQuestion']['question_type'] === '10') {
				// ファイルの場合
				/* @var $Nc2ToNc3Upload Nc2ToNc3Upload */
				$Nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
				// $nc2AnswerValueは[?action=common_download_chief&upload_id=10]形式
				if (preg_match('/upload_id=(\d+)$/', $nc2AnswerValue, $matches)) {
					$answerArr = [
						'answer_value_file' => $Nc2ToNc3Upload->generateUploadFile($matches[1]),
					];
				} else {
					$answerArr = [
						'answer_value' => '',
					];
				}
			} else {
				$answerArr = [
					'answer_value' => $this->_convertAnswerValue($nc2AnswerValue, $registrationMap[$nc2ItemId]),
				];
			}

			$data['RegistrationAnswer'][$nc3QuestionKey]['0'] = [
					'registration_question_key' => $nc3QuestionKey,
					'matrix_choice_key' => '',
				] + $answerArr;
		}

		return $data;
	}

/**
 * Generate Nc3RegistrationPage data.
 *
 * @param array $nc2Registration Nc2Registration data.
 * @return array Nc3RegistrationPage data.
 */
	private function __generateNc3RegistrationPageData($nc2Registration) {
		/* @var $Nc2Item AppModel */
		$Nc2Item = $this->_getNc2Model('registration_item');
		$nc2Items = $Nc2Item->findAllByRegistrationId(
			$nc2Registration['Nc2Registration']['registration_id'],
			null,
			'item_sequence',
			null,
			null,
			-1
		);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];
		$nc3PageSequence = 0;
		$nc3ItemSequence = 0;
		foreach ($nc2Items as $nc2Item) {
			$nc2ItemType = $nc2Item['Nc2RegistrationItem']['item_type'];
			$isNotTextType = in_array($nc2ItemType, ['2', '3', '4']);
			$nc3Item = [
				'question_sequence' => $nc3ItemSequence,
				'question_value' => $nc2Item['Nc2RegistrationItem']['item_name'],
				'question_type' => $this->_convertQuestionType($nc2ItemType),
				'description' => $nc2Item['Nc2RegistrationItem']['description'],
				'is_require' => $nc2Item['Nc2RegistrationItem']['require_flag'],
				'question_type_option' => '',
				'is_result_display' => $isNotTextType ? '1' : '0',
				'result_display_type' => '0',
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Item['Nc2RegistrationItem']),
				'created' => $this->_convertDate($nc2Item['Nc2RegistrationItem']['insert_time']),
			];

			if ($isNotTextType) {
				$nc3Item['RegistrationChoice'] = $this->__generateNc3RegistrationChoiceData($nc2Item);
			}

			$data[$nc3PageSequence]['page_sequence'] = $nc3PageSequence;
			$data[$nc3PageSequence]['RegistrationQuestion'][$nc3ItemSequence] = $nc3Item;

			$nc3ItemSequence++;
		}

		return $data;
	}

/**
 * Generate Nc3RegistrationChoice data.
 *
 * @param array $nc2Item Nc2RegistrationItemData data.
 * @return array Nc3RegistrationChoice data.
 */
	private function __generateNc3RegistrationChoiceData($nc2Item) {
		$nc2Choices = explode('|', $nc2Item['Nc2RegistrationItem']['option_value']);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];
		$nc3ChoiceSequence = 0;
		foreach ($nc2Choices as $nc2Choice) {
			$data[] = [
				'choice_sequence' => $nc3ChoiceSequence,
				'choice_label' => $nc2Choice,
				'graph_color' => $this->_getGraphColor($nc3ChoiceSequence),
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Item['Nc2RegistrationItem']),
				'created' => $this->_convertDate($nc2Item['Nc2RegistrationItem']['insert_time']),
			];
			$nc3ChoiceSequence++;
		}

		return $data;
	}

/**
 * Get map
 *
 * @param array|string $nc2RegistrationIds Nc2CRegistration registration_id.
 * @return array Map data with Nc2CRegistration questionnaire_id as key.
 */
	protected function _getMap($nc2RegistrationIds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Registration Registration */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Registration = ClassRegistry::init('Registrations.Registration');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Registration', $nc2RegistrationIds);
		$query = [
			'fields' => [
				'Registration.id',
				'Registration.key',
			],
			'conditions' => [
				'Registration.id' => $mapIdList,
			],
			'recursive' => -1,
			'callbacks' => false,
		];
		$nc3Registrations = $Registration->find('all', $query);
		if (!$nc3Registrations) {
			return $nc3Registrations;
		}

		$map = [];
		foreach ($nc3Registrations as $nc3Registration) {
			$nc2Id = array_search($nc3Registration['Registration']['id'], $mapIdList);
			$map[$nc2Id] = $nc3Registration;
		}

		if (is_string($nc2RegistrationIds)) {
			$map = $map[$nc2RegistrationIds];
		}

		return $map;
	}

/**
 * Get question map
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2ItemDataFirst Nc2RegistrationItemData data.
 * @param array $nc3Registration Nc3Registration data.
 * @return array Map data with RegistrationQuestion nc2RegistrationItem.item_id as key.
 */
	public function getQuestionMap(Model $model, $nc2ItemDataFirst, $nc3Registration) {
		$nc2RegistrationId = $nc2ItemDataFirst['Nc2RegistrationItemData']['registration_id'];

		/* @var $Nc2RegistrationItem AppModel */
		$Nc2Item = $this->_getNc2Model('registration_item');
		$nc2Items = $Nc2Item->findAllByRegistrationId(
			$nc2RegistrationId,
			[
				'item_id',
				'item_sequence',
			],
			'item_sequence',
			null,
			null,
			-1
		);
		$query = [
			'fields' => [
				'item_id',
				'item_sequence',
				'registration_id',
				'option_value',
			],
			'conditions' => [
				'registration_id' => $nc2RegistrationId,
				'option_value !=' => '',
			],
			'order' => [
				'item_id',
				'item_sequence',
			],
			'recursive' => -1,
		];
		$nc2ChoiceListTmp = $Nc2Item->find('all', $query);
		$nc2ChoiceList = [];
		foreach ($nc2ChoiceListTmp as $nc2Choice) {
			$nc2ChoiceList[$nc2Choice['Nc2RegistrationItem']['item_id']]
				= explode('|', $nc2Choice['Nc2RegistrationItem']['option_value']);
		}

		// $nc3RegistrationのRegistrationPage階層を除去RegistrationQuestion
		$nc3Questions = [];
		foreach ($nc3Registration['RegistrationPage'] as $nc3QuestionsEachPage) {
			// 数値添字なのでarray_mergeで追加される
			$nc3Questions = array_merge($nc3Questions, $nc3QuestionsEachPage['RegistrationQuestion']);
		}

		// 対応チェック（あり得ない気がするが一応）
		if (count($nc2Items) != count($nc3Questions)) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2ItemDataFirst));
			$this->_writeMigrationLog($message);

			return [];
		}

		$map = [];
		foreach ($nc2Items as $key => $nc2Item) {
			$nc2ItemId = $nc2Item['Nc2RegistrationItem']['item_id'];
			$map[$nc2ItemId]['RegistrationQuestion'] = [
				'id' => $nc3Questions[$key]['id'],
				'key' => $nc3Questions[$key]['key'],
				'question_type' => $nc3Questions[$key]['question_type'],
			];

			if (!isset($nc2ChoiceList[$nc2ItemId])) {
				continue;
			}

			$nc2ChoiceSeqList = $nc2ChoiceList[$nc2ItemId];
			$nc3Choices = $nc3Questions[$key]['RegistrationChoice'];
			// 対応チェック（あり得ない気がするが一応）
			if (count($nc2ChoiceSeqList) != count($nc3Choices)) {
				$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2ItemDataFirst));
				$this->_writeMigrationLog($message);

				return [];
			}

			$map[$nc2ItemId]['RegistrationChoice'] = $this->__getChoiceMap($nc3Choices);
			if (!$map[$nc2ItemId]['RegistrationChoice']) {
				$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2ItemDataFirst));
				$this->_writeMigrationLog($message);

				return [];
			}
		}

		return $map;
	}

/**
 * Get choice map
 *
 * @param array $nc3Choices RegistrationChoice data.
 * @return array Map data with RegistrationChoice choice_label as key.
 */
	private function __getChoiceMap($nc3Choices) {
		$map = [];
		foreach ($nc3Choices as $nc3Choice) {
			$map[$nc3Choice['choice_label']] = [
				'id' => $nc3Choice['id'],
				'key' => $nc3Choice['key'],
				'choice_label' => $nc3Choice['choice_label'],
			];
		}

		return $map;
	}

/**
 * Get choice map
 *
 * @param string $questionType nc2RegistrationQuestion.question_type data.
 * @return string data with RegistrationQuestion.question_type.
 */
	protected function _convertQuestionType($questionType) {
		// nc2 => nc3
		$map = [
			'1' => '3', // テキスト
			'3' => '1', // 択一式
			'2' => '2', // 複数選択
			'4' => '8', // リストボックス
			'5' => '4', // 記述式
			'6' => '9', // メール
			'7' => '10', // ファイル
		];

		return $map[$questionType];
	}

/**
 * Convert answer value.
 *
 * @param string $nc2AnswerValue Nc2 answer value.
 * @param array $registrationMap registrationMap data.
 * @return string graph_color code
 */
	protected function _convertAnswerValue($nc2AnswerValue, $registrationMap) {
		if (!isset($registrationMap['RegistrationChoice'])) {
			return $nc2AnswerValue;
		}

		$nc2Choices = explode('|', $nc2AnswerValue);
		$nc3AnswerValue = '';
		$nc3AnswerArray = [];
		//foreach ($nc2Choices as $key => $nc2Choice) {
		//phpmdで　 Avoid unused local variables such as '$key'　が出力されたので対応
		foreach ($nc2Choices as $nc2Choice) {
			if (empty($nc2Choice)) {
				continue;
			}
			$nc3Choice = $registrationMap['RegistrationChoice'][$nc2Choice];
			$nc3AnswerValue = '|' . $nc3Choice['key'] . ':' . $nc3Choice['choice_label'];
			// 複数選択の場合は配列化
			if ($registrationMap['RegistrationQuestion']['question_type'] === '2') {
				$nc3AnswerArray[] = $nc3AnswerValue;
			}
		}

		if ($nc3AnswerArray) {
			return $nc3AnswerArray;
		}

		return $nc3AnswerValue;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Registration Array data of Nc2Registration.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Registration) {
		if (isset($nc2Registration['Nc2Registration'])) {
			return 'Nc2Registration ' .
				'registration_id:' . $nc2Registration['Nc2Registration']['registration_id'];
		}

		if (isset($nc2Registration['Nc2RegistrationBlock'])) {
			return 'Nc2RegistrationBlock ' .
				'block_id:' . $nc2Registration['Nc2RegistrationBlock']['block_id'];
		}

		if (isset($nc2Registration['Nc2RegistrationData'])) {
			return 'Nc2RegistrationData' .
				'data_id:' . $nc2Registration['Nc2RegistrationData']['data_id'];
		}

		if (isset($nc2Registration['Nc2RegistrationItem'])) {
			return 'Nc2RegistrationItem' .
				'item_id:' . $nc2Registration['Nc2RegistrationItem']['item_id'];
		}

		return 'Nc2RegistrationItemData' .
			'item_data_id:' . $nc2Registration['Nc2RegistrationItemData']['item_data_id'];
	}

}
