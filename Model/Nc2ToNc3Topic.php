<?php
/**
 * Nc2ToNc3Topic
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Topic
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
 * @see Nc2ToNc3WhatsnewBehavior
 * @method string getLogArgument($nc2WhatsnewBlock)
 * @method array generateNc3WhatsnewData($frameMap, $nc2Whatsnew)
 * @method array generateNc3WhatsnewQuestionData($nc3Whatsnew, $nc2Question)
 * @method array generateNc3TopicFrameSettingData($nc2WhatsnewBlock)
 *
 */
class Nc2ToNc3Topic extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Topic'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Topic Migration start.'));

		/* @var $Nc2WhatsnewBlock AppModel */
		$Nc2WhatsnewBlock = $this->getNc2Model('whatsnew_block');
		$nc2WhatsnewBlocks = $Nc2WhatsnewBlock->find('all');
		if (!$this->__saveTopicBlockFromNc2($nc2WhatsnewBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Topic Migration end.'));

		return true;
	}

/**
 * Save TopicFrameSetting from Nc2.
 *
 * @param array $nc2WhatsnewBlocks Nc2Whatsnew data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveTopicBlockFromNc2($nc2WhatsnewBlocks) {
		/* @var $TopicFrameSetting TopicFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$TopicFrameSetting = ClassRegistry::init('Topics.TopicFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');

		foreach ($nc2WhatsnewBlocks as $nc2WhatsnewBlock) {
			$TopicFrameSetting->begin();
			try {
				$data = $this->generateNc3TopicFrameSettingData($nc2WhatsnewBlock);
				if (!$data) {
					$TopicFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2WhatsnewBlock['Nc2WhatsnewBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				$this->writeCurrent($frameMap, 'Topics');

				if (!$TopicFrameSetting->saveTopicFrameSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$TopicFrameSetting->rollback();

					$message = $this->getLogArgument($nc2WhatsnewBlock) . "\n" .
						var_export($TopicFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$TopicFrameSetting->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$idMap = [
					$nc2BlockId => $TopicFrameSetting->id,
				];
				$this->saveMap('TopicFrameSetting', $idMap);

				$TopicFrameSetting->commit();

			} catch (Exception $ex) {
				$TopicFrameSetting->rollback($ex);
				throw $ex;
			}
		}
		$this->removeUseCurrent();

		return true;
	}

}