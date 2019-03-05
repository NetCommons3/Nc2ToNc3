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
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Faq',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Faq Migration start.'));

		/* @var $Nc2ToNc3Plugin Nc2ToNc3Plugin */
		$Nc2ToNc3Plugin = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Plugin');
		$pluginMap = $Nc2ToNc3Plugin->getMap();
		if (!Hash::extract($pluginMap, '{n}.Plugin[key=faqs]')) {
			$this->writeMigrationLog(__d('nc2_to_nc3', 'Faq is not installed.'));
			return true;
		}

		/* @var $Nc2Faq AppModel */
		$Nc2Faq = $this->getNc2Model('faq');
		$nc2Faqs = $Nc2Faq->find('all');
		if (!$this->__saveFaqFromNc2($nc2Faqs)) {
			return false;
		}

		/* @var $Nc2FaqQuestion AppModel */
		$Nc2FaqQuestion = $this->getNc2Model('faq_question');
		$query['order'] = [
			'faq_id',
			'display_sequence'
		];
		$nc2Questions = $Nc2FaqQuestion->find('all', $query);
		if (!$this->__saveFaqQuestionFromNc2($nc2Questions)) {
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
		/* @var $Nc2FaqBlock AppModel */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Faq = ClassRegistry::init('Faqs.Faq');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Faq->Behaviors->Block->settings = $Faq->actsAs['Blocks.Block'];

		$Nc2FaqBlock = $this->getNc2Model('faq_block');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');
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
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2Faq));
					$this->writeMigrationLog($message);
					$Faq->rollback();
					continue;
				}

				$data = $this->generateNc3FaqData($frameMap, $nc2Faq);
				if (!$data) {
					$Faq->rollback();
					continue;
				}

				$query['conditions'] = [
					'faq_id' => $nc2Faq['Nc2Faq']['faq_id']
				];
				$nc2CategoryList = $Nc2ToNc3Category->getNc2CategoryList('faq_category', $query);
				$data['Categories'] = $Nc2ToNc3Category->generateNc3CategoryData($nc2CategoryList);

				$this->writeCurrent($frameMap, 'faqs');

				$BlocksLanguage->create();
				if (!$Faq->saveFaq($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Faq->rollback();

					$message = $this->getLogArgument($nc2Faq) . "\n" .
						var_export($Faq->validationErrors, true);
					$this->writeMigrationLog($message);

					$Faq->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2FaqId = $nc2Faq['Nc2Faq']['faq_id'];
				$idMap = [
					$nc2FaqId => $Faq->id,
				];
				$this->saveMap('Faq', $idMap);

				$nc3Faq = $Faq->findById($Faq->id, 'block_id', null, -1);
				if (!$Nc2ToNc3Category->saveCategoryMap($nc2CategoryList, $nc3Faq['Faq']['block_id'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Faq);
					$this->writeMigrationLog($message);

					$Faq->rollback();
					continue;
				}

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
 * Save FaqQuestion from Nc2.
 *
 * @param array $nc2Questions Nc2Question data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveFaqQuestionFromNc2($nc2Questions) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqQuestion data Migration start.'));

		/* @var $FaqQuestion FaqQuestion */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$FaqQuestion = ClassRegistry::init('Faqs.FaqQuestion');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Nc2ToNc3Category = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Category');

		Current::write('Plugin.key', 'faqs');
		foreach ($nc2Questions as $nc2Question) {
			$FaqQuestion->begin();
			try {
				$data = $this->generateNc3FaqQuestionData($nc2Question);
				if (!$data) {
					$FaqQuestion->rollback();
					continue;
				}

				$nc3BlockId = $data['FaqQuestion']['block_id'];
				$nc2CategoryId = $nc2Question['Nc2FaqQuestion']['category_id'];
				$data['FaqQuestion']['category_id'] = $Nc2ToNc3Category->getNc3CategoryId($nc3BlockId, $nc2CategoryId);

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block.id', $nc3BlockId);

				$nc2RoomId = $nc2Question['Nc2FaqQuestion']['room_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomId);
				$nc3RoomId = $mapIdList[$nc2RoomId];
				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// 一応Model::validatの初期化
				$FaqQuestion->validate = [];

				if (!$FaqQuestion->saveFaqQuestion($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$FaqQuestion->rollback();

					$message = $this->getLogArgument($nc2Question) . "\n" .
							var_export($FaqQuestion->validationErrors, true);
					$this->writeMigrationLog($message);

					$FaqQuestion->rollback();
					continue;
				}

				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2FaqQuestionId = $nc2Question['Nc2FaqQuestion']['question_id'];
				$idMap = [
					$nc2FaqQuestionId => $FaqQuestion->id,
				];
				$this->saveMap('FaqQuestion', $idMap);

				$FaqQuestion->commit();

			} catch (Exception $ex) {
				$FaqQuestion->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Block.id');
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqQuestion data Migration end.'));

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
		/* @var $Frame Frame */
		$FaqFrameSetting = ClassRegistry::init('Faqs.FaqFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2FaqBlocks as $nc2FaqBlock) {
			$FaqFrameSetting->begin();
			try {
				$data = $this->generateNc3FaqFrameSettingData($nc2FaqBlock);
				if (!$data) {
					$FaqFrameSetting->rollback();
					continue;
				}

				if (!$FaqFrameSetting->saveFaqFrameSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$FaqFrameSetting->rollback();

					$message = $this->getLogArgument($nc2FaqBlock) . "\n" .
						var_export($FaqFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$FaqFrameSetting->rollback();
					continue;
				}

				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。 var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2FaqBlock) . "\n" .
						var_export($Frame->validationErrors, true);
					$this->writeMigrationLog($message);

					$FaqFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2FaqBlock['Nc2FaqBlock']['block_id'];
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

		$this->writeMigrationLog(__d('nc2_to_nc3', '  FaqFrameSetting data Migration end.'));

		return true;
	}
}

