<?php
/**
 * Nc2ToNc3FaqBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3FaqBehavior
 *
 */
class Nc2ToNc3FaqBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Faq Array data of Nc2Faq.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Faq) {
		return $this->__getLogArgument($nc2Faq);
	}

/**
 * Generate Nc3Faq data.
 *
 * Data sample
 * data[Frame][id]:
 * data[Block][id]:
 * data[Block][key]:
 * data[Block][room_id]:
 * data[Block][plugin_key]:faqs
 * data[Block][name]:
 * data[Block][public_type]:1
 * data[Faq][id]:
 * data[Faq][key]:
 * data[Faq][name]:
 * data[Faq][created_user]:
 * data[Faq][created]:
 * data[Categories]:
 * data[Topics][plugin_key]:faqs
 *
 * @param Model $model Model using this behavior.
 * @param array $frameMap Frame mapping data.
 * @param array $nc2Faq Nc2Faq data.
 * @return array Nc3Faq data.
 */
	public function generateNc3FaqData(Model $model, $frameMap, $nc2Faq) {
		$nc2FaqId = $nc2Faq['Nc2Faq']['faq_id'];
		$faqMap = $this->_getMap($nc2FaqId);
		if ($faqMap) {
			// 移行済みの場合
			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['Frame'] = [
			'id' => $frameMap['Frame']['id'],
		];
		$data['Block'] = [
			'id' => '',
			'key' => '',
			'room_id' => $frameMap['Frame']['room_id'],
			'plugin_key' => 'faqs',
			'name' => $nc2Faq['Nc2Faq']['faq_name'],
			'public_type' => 1,
		];
		$data['Faq'] = [
			'id' => '',
			'key' => '',
			'name' => $nc2Faq['Nc2Faq']['faq_name'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Faq['Nc2Faq']),
			'created' => $this->_convertDate($nc2Faq['Nc2Faq']['insert_time']),
		];

		/* @var $Nc2FaqCategory AppModel */
		$Nc2FaqCategory = $this->getNc2Model($model, 'faq_category');
		$nc2Categories = $Nc2FaqCategory->findAllByFaqId(
			$nc2Faq['Nc2Faq']['faq_id'],
			null,
			['display_sequence' => 'ASC'],
			-1
		);
		$data['Categories'] = $this->_generateNc3CategoryData($nc2Categories);

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
		$data['Topic'] = [
			'plugin_key' => 'faqs',
		];

		return $data;
	}

/**
 * Get Nc2 Model.
 *
 * @param array $nc2Categories Nc2Categories table name.
 * @return array Category model.
 */
	protected function _generateNc3CategoryData($nc2Categories) {
		$result = [];
		foreach ($nc2Categories as $nc2Category) {
			$data = [
				'Category' => [
					'id' => '',
					'block_id' => '',
					'key' => '',
				],
				'CategoriesLanguage' => [
					'id' => '',
					'name' => $nc2Category['Nc2FaqCategory']['category_name'],
				],
				'CategoryOrder' => [
					'id' => '',
					'weight' => $nc2Category['Nc2FaqCategory']['display_sequence'],
					'block_key' => '',
				],
			];
			$result[] = $data;
		}

		return $result;
	}

/**
 * Generate Nc3FaqQuestion data.
 *
 * Data sample
 * data[Faq][key]:
 * data[FaqQuestion][id]:
 * data[FaqQuestion][faq_key]:
 * data[FaqQuestion][key]:
 * data[FaqQuestion][block_id]:
 * data[FaqQuestion][language_id]:
 * data[FaqQuestion][category_id]:0
 * data[FaqQuestion][question]:新規質問1
 * data[FaqQuestion][answer]:新規回答1
 * data[FaqQuestionOrder][id]:0
 * data[FaqQuestionOrder][faq_key]:
 * data[FaqQuestionOrder][faq_question_key]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc3Faq Faq data.
 * @param array $nc2FaqQuestion Nc2FaqQuestion data.
 * @return array Nc3FaqQuestion data.
 */
	public function generateNc3FaqQuestionData(Model $model, $nc3Faq, $nc2FaqQuestion) {
		$data = [
			'Faq' => [
				'key' => $nc3Faq['Faq']['key'],
			],
			'FaqQuestion' => [
				'id' => '',
				'faq_key' => $nc3Faq['Faq']['key'],
				'key' => '',
				'block_id' => $nc3Faq['Block']['id'],
				'status' => '1',
				'language_id' => '',
				'category_id' => '', // TODOーカテゴリを設定する
				'question' => $nc2FaqQuestion['Nc2FaqQuestion']['question_name'],
				'answer' => $nc2FaqQuestion['Nc2FaqQuestion']['question_answer'],
			],
			'FaqQuestionOrder' => [
				'id' => '',
				'faq_key' => $nc3Faq['Faq']['key'],
				'faq_question_key' => '',
			],
		];

		return $data;
	}

/**
 * Generate Nc3FaqFrameSetting data.
 *
 * Data sample
 * data[FaqFrameSetting][id]:
 * data[FaqFrameSetting][frame_key]:
 * data[FaqFrameSetting][content_per_page]:1
 * data[FaqFrameSetting][created_user]:
 * data[FaqFrameSetting][created]:
 * data[FaqSetting][use_workflow]:
 * data[FaqSetting][use_like]:
 * data[FaqSetting][use_unlike]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2FaqBlock Nc2FaqBlock data.
 * @return array Nc3Faq data.
 */
	public function generateNc3FaqFrameSettingData(Model $model, $nc2FaqBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2FaqBlock['Nc2FaqBlock']['block_id'];
		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2FaqBlock));
			$this->_writeMigrationLog($message);

			return [];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data['FaqFrameSetting'] = [
			'id' => '',
			'frame_key' => $frameMap['Frame']['key'],
			'content_per_page' => $nc2FaqBlock['Nc2FaqBlock']['display_row'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2FaqBlock['Nc2FaqBlock']),
			'created' => $this->_convertDate($nc2FaqBlock['Nc2FaqBlock']['insert_time']),
		];
		$data['FaqSetting'] = [
			'use_workflow' => '0',
			'use_like' => '0',
			'use_unlike' => '0',
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Faq Array data of Nc2FaqBlock, Nc2Faq and Nc2FaqQuestion.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Faq) {
		if (isset($nc2Faq['Nc2FaqBlock'])) {
			return 'Nc2FaqBlock ' .
				'block_id:' . $nc2Faq['Nc2FaqBlock']['block_id'];
		}

		if (isset($nc2Faq['Nc2FaqQuestion'])) {
			return 'Nc2FaqQuestion' .
				'faq_id:' . $nc2Faq['Nc2FaqQuestion']['faq_id'] . ',' .
				'question_id:' . $nc2Faq['Nc2FaqQuestion']['question_id'];
		}

		return 'Nc2Faq ' .
			'faq_id:' . $nc2Faq['Nc2Faq']['faq_id'] . ',' .
			'faq_name:' . $nc2Faq['Nc2Faq']['faq_name'];
	}

/**
 * Get map
 *
 * @param array|string $nc2FaqIds Nc2CFaq faq_id.
 * @return array Map data with Nc2Block block_id as key.
 */
	protected function _getMap($nc2FaqIds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Faq Faq */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Faq = ClassRegistry::init('Faqs.Faq');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Faq', $nc2FaqIds);
		$query = [
			'fields' => [
				'Faq.id',
				'Faq.key',
			],
			'conditions' => [
				'Faq.id' => $mapIdList,
			],
			'recursive' => -1,
			'callbacks' => false,
		];
		$nc3Faqs = $Faq->find('all', $query);
		if (!$nc3Faqs) {
			return $nc3Faqs;
		}

		$map = [];
		foreach ($nc3Faqs as $nc3Faq) {
			$nc2Id = array_search($nc3Faq['Faq']['id'], $mapIdList);
			$map[$nc2Id] = $nc3Faq;
		}

		if (is_string($nc2FaqIds)) {
			$map = $map[$nc2FaqIds];
		}

		return $map;
	}
}
