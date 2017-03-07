<?php
/**
 * Nc2ToNc3Calendar
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Calendar
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
 * @see Nc2ToNc3CalendarBehavior
 * @method string getLogArgument($nc2Calendar)
 * @method string generateNc3CalendarFrameSettingData($nc2CalendarBlock)
 *
 */
class Nc2ToNc3Calendar extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Calendar'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Calendar Migration start.'));

		/* @var $Nc2CalendarManage AppModel
		$Nc2CalendarManage = $this->getNc2Model('calendar_manage');
		$nc2CalendarManages = $Nc2CalendarManage->find('all');
		if (!$this->__saveBlockRolePermissionFromNc2($nc2CalendarManages)) {
			return false;
		} */

		/* @var $Nc2CalendarBlock AppModel */
		$Nc2CalendarBlock = $this->getNc2Model('calendar_block');
		$nc2CalendarBlocks = $Nc2CalendarBlock->find('all');
		if (!$this->__saveCalendarFrameSettingFromNc2($nc2CalendarBlocks)) {
			return false;
		}

		/* @var $Nc2CalendarPlan AppModel
		$Nc2CalendarPlan = $this->getNc2Model('calendar_plan');
		$nc2CalendarPlans = $Nc2CalendarPlan->find('all');
		if (!$this->__saveCalendarEventFromNc2($nc2CalendarPlans)) {
			return false;
		} */

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Calendar Migration end.'));
		return true;
	}

/**
 * Save CalendarFrameSetting from Nc2.
 *
 * @param array $nc2CalendarBlocks Nc2CalendarBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveCalendarFrameSettingFromNc2($nc2CalendarBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarFrameSetting data Migration start.'));

		/* @var $CalendarFrameSetting CalendarFrameSetting */
		$CalendarFrameSetting = ClassRegistry::init('Calendars.CalendarFrameSetting');
		foreach ($nc2CalendarBlocks as $nc2CalendarBlock) {
			$CalendarFrameSetting->begin();
			try {
				$data = $this->generateNc3CalendarFrameSettingData($nc2CalendarBlock);
				if (!$data) {
					continue;
				}

				if (!$CalendarFrameSetting->saveFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2CalendarBlocks) . "\n" .
						var_export($CalendarFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				$nc2BlockId = $nc2CalendarBlock['Nc2CalendarBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $CalendarFrameSetting->id
				];
				$this->saveMap('CalendarFrameSetting', $idMap);

				$CalendarFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CalendarFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$CalendarFrameSetting->saveFrameSetting($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarFrameSetting data Migration end.'));

		return true;
	}
}

