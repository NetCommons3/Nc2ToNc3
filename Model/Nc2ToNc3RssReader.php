<?php
/**
 * Nc2ToNc3RssReader
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3RssReader
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
 * @see Nc2ToNc3RssReaderBehavior
 * @method string getLogArgument($nc2RssBlock)
 * @method array generateNc3RssReaderData($frameMap, $nc2RssBlock)
 * @method array generateNc3FrameSettingData($frameMap, $nc2RssBlock)
 *
 */
class Nc2ToNc3RssReader extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3RssReader'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'RssReader Migration start.'));

		/* @var $Nc2RssBlock AppModel */
		$Nc2RssBlock = $this->getNc2Model('rss_block');
		$nc2RssBlocks = $Nc2RssBlock->find('all');
		if (!$this->__saveRssFromNc2($nc2RssBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'RssReader Migration end.'));

		return true;
	}

/**
 * Save RssReader from Nc2.
 *
 * @param array $nc2RssBlocks Nc2RssBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveRssFromNc2($nc2RssBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  RssReader data Migration start.'));

		/* @var $Frame Frame */
		/* @var $RssReader RssReader */
		/* @var $RssReaderFrameSetting RssReaderFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $BlocksLanguage BlocksLanguage */
		$RssReader = ClassRegistry::init('RssReaders.RssReader');
		$RssReaderFrameSet = ClassRegistry::init('RssReaders.RssReaderFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		foreach ($nc2RssBlocks as $nc2RssBlock) {
			$RssReader->begin();
			try {
				$nc2BlockId = $nc2RssBlock['Nc2RssBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				$this->writeCurrent($frameMap, 'rss_readers');

				$data = $this->generateNc3RssReaderData($frameMap, $nc2RssBlock);
				if (!$data) {
					$RssReader->rollback();
					continue;
				}

				$Frame->create();
				$BlocksLanguage->create();
				$RssReader->create();
				if (!$RssReader->saveRssReader($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$RssReader->rollback();
					$message = $this->getLogArgument($nc2RssBlock) . "\n" .
						var_export($RssReader->validationErrors, true);
					$this->writeMigrationLog($message);

					$RssReader->rollback();
					continue;
				}

				$data = $this->generateNc3FrameSettingData($frameMap, $nc2RssBlock);
				if (!$data) {
					$RssReader->rollback();
					continue;
				}

				if (!$RssReaderFrameSet->saveRssReaderFrameSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$RssReaderFrameSet->rollback();

					$message = $this->getLogArgument($nc2RssBlock) . "\n" .
						var_export($RssReaderFrameSet->validationErrors, true);
					$this->writeMigrationLog($message);

					$RssReader->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2RssBlockId = $nc2RssBlock['Nc2RssBlock']['block_id'];
				$idMap = [
					$nc2RssBlockId => $RssReader->id,
				];
				$this->saveMap('RssReader', $idMap);

				$RssReader->commit();

			} catch (Exception $ex) {
				$RssReader->rollback($ex);
				throw $ex;
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  RssReader data Migration end.'));

		return true;
	}
}
