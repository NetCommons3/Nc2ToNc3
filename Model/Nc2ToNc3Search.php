<?php
/**
 * Nc2ToNc3Search
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Search
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
 * @see Nc2ToNc3SearchBehavior
 * @method string getLogArgument($nc2SearchBlock)
 * @method array generateNc3SearchData($frameMap, $nc2SearchBlock)
 * @method array generateNc3FrameSettingData($frameMap, $nc2SearchBlock)
 *
 */
class Nc2ToNc3Search extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Search'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Search Migration start.'));

		/* @var $Nc2SearchBlocks AppModel */
		$Nc2SearchBlocks = $this->getNc2Model('search_blocks');
		$nc2SearchBlocks = $Nc2SearchBlocks->find('all');
		if (!$this->__saveSearchFromNc2($nc2SearchBlocks)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Search Migration end.'));

		return true;
	}

/**
 * Save Search from Nc2.
 *
 * @param array $nc2SearchBlocks Nc2SearchBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveSearchFromNc2($nc2SearchBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Search data Migration start.'));

		/* @var $Frame Frame */
		/* @var $SearchFrameSetting SearchFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$SearchFrameSetting = ClassRegistry::init('Searches.SearchFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach ($nc2SearchBlocks as $nc2SearchBlock) {
			$SearchFrameSetting->begin();
			try {
				$nc2BlockId = $nc2SearchBlock['Nc2SearchBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2SearchBlock));
					$this->writeMigrationLog($message);

					$SearchFrameSetting->rollback();
					continue;
				}

				$this->writeCurrent($frameMap, 'searches');

				$data = $this->generateNc3FrameSettingData($frameMap, $nc2SearchBlock);
				if (!$data) {
					$SearchFrameSetting->rollback();
					continue;
				}

				$Frame->create();
				if (!$SearchFrameSetting->saveSearchFrameSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$SearchFrameSetting->rollback();

					$message = $this->getLogArgument($nc2SearchBlock) . "\n" .
						var_export($SearchFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$SearchFrameSetting->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2SearchBlockId = $nc2SearchBlock['Nc2SearchBlock']['block_id'];
				$idMap = [
					$nc2SearchBlockId => $SearchFrameSetting->id,
				];
				$this->saveMap('Search', $idMap);

				$SearchFrameSetting->commit();

			} catch (Exception $ex) {
				$SearchFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		$this->removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Search data Migration end.'));

		return true;
	}
}
