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
 * @method array generateNc3FaqData($frameMap, $nc2Faq)
 * @method array generateNc3FaqQuestionData($nc3Faq, $nc2Question)
 * @method array generateNc3FaqFrameSettingData($nc2FaqBlock)
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

		/* @var $Nc2Faq AppModel */
		$Nc2Faq = $this->getNc2Model('faq');
		$nc2Faqs = $Nc2Faq->find('all');
		if (!$this->__saveFaqFromNc2($nc2Faqs)) {
			return false;
		}

		/* @var $Nc2FaqBlock AppModel */
		$Nc2FaqBlock = $this->getNc2Model('faq_block');
		$nc2FaqBlocks = $Nc2FaqBlock->find('all');
		if (!$this->__saveFaqBlockFromNc2($nc2FaqBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Faq Migration end.'));

		return true;
	}

/**
 * Save Faq from Nc2.
 *
 * @param array $nc2Faqs Nc2Faq data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveFaqFromNc2($nc2Faqs) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Faq data Migration start.'));

		/* @var $Faq Faq */
		/* @var $FaqQuestion FaqQuestion */
		/* @var $Nc2FaqBlock AppModel */
		/* @var $Nc2FaqQuestion AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $BlocksLanguage BlocksLanguage */
		$Faq = ClassRegistry::init('Faqs.Faq');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Faq->Behaviors->Block->settings = $Faq->actsAs['Blocks.Block'];

		$FaqQuestion = ClassRegistry::init('Faqs.FaqQuestion');
		$Nc2FaqBlock = $this->getNc2Model('faq_block');
		$Nc2FaqQuestion = $this->getNc2Model('faq_question');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		foreach ($nc2Faqs as $nc2Faq) {
			$Faq->begin();
			try {
				$nc2RoomId = $nc2Faq['Nc2Faq']['room_id'];
				$nc2FaqBlock = $Nc2FaqBlock->findByRoomId($nc2RoomId, 'block_id', null, -1);
				if (!$nc2FaqBlock) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Faq));
					$this->writeMigrationLog($message);
					$Faq->rollback();
					continue;
				}

				$frameMap = $Nc2ToNc3Frame->getMap($nc2FaqBlock['Nc2FaqBlock']['block_id']);

				$data = $this->generateNc3FaqData($frameMap, $nc2Faq);
				if (!$data) {
					$Faq->rollback();
					continue;
				}

				$this->writeCurrent($frameMap, 'faqs');

				$BlocksLanguage->create();
				if (!$Faq->saveFaq($data)) {
					$message = $this->getLogArgument($nc2Faq) . "\n" .
						var_export($Faq->validationErrors, true);
					$this->writeMigrationLog($message);

					$Faq->rollback();
					continue;
				}

				$nc2FaqId = $nc2Faq['Nc2Faq']['faq_id'];
				$nc2Questions = $Nc2FaqQuestion->findAllByFaqId($nc2FaqId, null, ['display_sequence' => 'ASC'], -1);
				$nc3Faq = $Faq->read();
				foreach ($nc2Questions as $nc2Question) {
					$data = $this->generateNc3FaqQuestionData($nc3Faq, $nc2Question);
					if (!$FaqQuestion->saveFaqQuestion($data)) {
						$message = $this->getLogArgument($nc2Question) . "\n" .
							var_export($Faq->validationErrors, true);
						$this->writeMigrationLog($message);

						$Faq->rollback();
						continue;
					}
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

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
		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Faq data Migration end.'));

		return true;
	}

/**
 * Save FaqFrameSetting from Nc2.
 *
 * @param array $nc2FaqBlocks Nc2Faq data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveFaqBlockFromNc2($nc2FaqBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqFrameSetting data Migration start.'));

		/* @var $FaqFrameSetting FaqFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$FaqFrameSetting = ClassRegistry::init('Faqs.FaqFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach ($nc2FaqBlocks as $nc2FaqBlock) {
			$FaqFrameSetting->begin();
			try {
				$data = $this->generateNc3FaqFrameSettingData($nc2FaqBlock);
				if (!$data) {
					$FaqFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2FaqBlock['Nc2FaqBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				$this->writeCurrent($frameMap, 'faqs');

				if (!$FaqFrameSetting->saveFaqFrameSetting($data)) {
					$message = $this->getLogArgument($nc2FaqBlock) . "\n" .
						var_export($FaqFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$FaqFrameSetting->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$idMap = [
					$nc2BlockId => $FaqFrameSetting->id,
				];
				$this->saveMap('FaqFrameSetting', $idMap);

				$FaqFrameSetting->commit();

			} catch (Exception $ex) {
				$FaqFrameSetting->rollback($ex);
				throw $ex;
			}
		}
		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqFrameSetting data Migration end.'));

		return true;
	}
}

