<?php
/**
 * Nc2ToNc3Faq
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Faq
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
 *
 * @see Nc2ToNc3FaqBehavior
 * @method string getLogArgument($nc2FaqBlock)
 * @method array generateNc3FaqData($nc2Faq, $nc2Categories)
 * @method array generateNc3FaqQuestionData($nc3Faq, $nc2Question, $nc2Categories)
 *
 */
class Nc2ToNc3Faq extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Faq'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Faq Migration start.'));

		/* @var $Nc2FaqBlock AppModel */
		$Nc2FaqBlock = $this->getNc2Model('faq_block');
		$nc2FaqBlocks = $Nc2FaqBlock->find('all');

		if (!$this->__saveFaqFromNc2($nc2FaqBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Faq Migration end.'));

		return true;
	}

/**
 * Save FaqBlock from Nc2.
 *
 * @param array $nc2FaqBlocks Nc2Faq data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveFaqFromNc2($nc2FaqBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqBlock data Migration start.'));

		/* @var $Faq Faq */
		/* @var $FaqFrameSetting FaqFrameSetting */
		/* @var $FaqQuestion FaqQuestion */
		/* @var $Nc2FaqCategory AppModel */
		/* @var $Nc2FaqQuestion AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Faq = ClassRegistry::init('Faqs.Faq');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Faq->Behaviors->Block->settings = $Faq->actsAs['Blocks.Block'];

		$FaqFrameSetting = ClassRegistry::init('Faqs.FaqFrameSetting');
		$FaqQuestion = ClassRegistry::init('Faqs.FaqQuestion');
		$Nc2FaqCategory = $this->getNc2Model('faq_category');
		$Nc2FaqQuestion = $this->getNc2Model('faq_question');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach ($nc2FaqBlocks as $nc2FaqBlock) {
			$nc2Categories = $Nc2FaqCategory->findAllByFaqId($nc2FaqBlock['Nc2FaqBlock']['faq_id'], null, ['display_sequence' => 'ASC'], -1);
			$Faq->begin();
			try {
				$data = $this->generateNc3FaqData($nc2FaqBlock, $nc2Categories);
				if (!$data) {
					$Faq->rollback();
					continue;
				}

				$frameMap = $Nc2ToNc3Frame->getMap($nc2FaqBlock['Nc2FaqBlock']['block_id']);
				$nc3RoomId = $frameMap['Frame']['room_id'];
				Current::write('Frame.key', $frameMap['Frame']['key']);
				Current::write('Frame.room_id', $nc3RoomId);
				Current::write('Frame.plugin_key', 'faqs');

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
				Current::write('Plugin.key', 'faqs');

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				if (!$Faq->saveFaq($data)) {
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2FaqBlock) . "\n" .
						var_export($Faq->validationErrors, true);
					$this->writeMigrationLog($message);

					$Faq->rollback();
					continue;
				}

				$faqFrameSettingData = [
					'FaqFrameSetting' => $data['FaqFrameSetting'],
				];

				if (!$FaqFrameSetting->saveFaqFrameSetting($faqFrameSettingData)) {
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2FaqBlock) . "\n" .
						var_export($Faq->validationErrors, true);
					$this->writeMigrationLog($message);

					$Faq->rollback();
					continue;
				}

				$nc3Faq = $Faq->read();
				$nc2Questions = $Nc2FaqQuestion->findAllByFaqId(
					$nc2FaqBlock['Nc2FaqBlock']['faq_id'],
					null,
					['display_sequence' => 'ASC'],
					-1
				);
				foreach ($nc2Questions as $nc2Question) {
					$data = $this->generateNc3FaqQuestionData($nc3Faq, $nc2Question, $nc2Categories);
					if (!$FaqQuestion->saveFaqQuestion($data)) {
						// @see https://phpmd.org/rules/design.html
						$message = $this->getLogArgument($nc2FaqBlock) . "\n" .
							var_export($Faq->validationErrors, true);
						$this->writeMigrationLog($message);

						$Faq->rollback();
						continue;
					}
				}

				// 登録処理で使用しているデータを空に戻す
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2FaqId = $nc2FaqBlock['Nc2FaqBlock']['faq_id'];
				$idMap = [
					$nc2FaqId => $Faq->id,
				];
				$this->saveMap('Faq', $idMap);

				$Faq->commit();

			} catch (Exception $ex) {
				$Faq->rollback($ex);
				throw $ex;
			}
		}

		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Plugin.key');
		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqBlock data Migration end.'));

		return true;
	}
}

