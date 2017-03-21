<?php
/**
 * Nc2ToNc3Cabinet
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Cabinet
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
 */
class Nc2ToNc3Cabinet extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Cabinet'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Cabinet Migration start.'));

		/* @var $Nc2Cabinet AppModel */
		/* @var $Nc2CabinetBlock AppModel */
		$Nc2CabinetManage = $this->getNc2Model('cabinet_manage');
		$nc2CabinetManages = $Nc2CabinetManage->find('all');

		if (!$this->__saveNc3CabinetFromNc2($nc2CabinetManages)) {
			return false;
		}
		//親子関係を維持するため、File ID順に取得
		$query = [
			'order' => [
				'depth',
			],
		];

		$Nc2CabinetFile = $this->getNc2Model('cabinet_file');
		$nc2CabinetFiles = $Nc2CabinetFile->find('all', $query);

		if (!$this->__saveNc3CabinetFileNc2($nc2CabinetFiles)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Cabinet Migration end.'));
		return true;
	}

/**
 * Save JournalFrameSetting from Nc2.
 *
 * @param array $nc2CabinetManages Nc2CabinetManage data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3CabinetFromNc2($nc2CabinetManages) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Cabinet data Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$Cabinet = ClassRegistry::init('Cabinets.Cabinet');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Cabinet->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Cabinet->Behaviors->Block->settings = $Cabinet->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');

		foreach ($nc2CabinetManages as $nc2CabinetManage) {

			$Nc2CabinetBlock = $this->getNc2Model('cabinet_block');
			//$nc2CabinetBlock = $Nc2CabinetBlock->find('all');

			$nc2CabinetBlock = $Nc2CabinetBlock->findByRoomId($nc2CabinetManage['Nc2CabinetManage']['room_id'], null, null, -1);
			if (!$nc2CabinetBlock) {
				$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2CabinetBlock));
				$this->_writeMigrationLog($message);
				continue;
			}

			$Cabinet->begin();
			try {
				$data = $this->generateNc3CabinetData($nc2CabinetManage, $nc2CabinetBlock);
				if (!$data) {
					$Cabinet->rollback();
					continue;
				}

				$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				$nc3Room = $Nc2ToNc3Room->getMap($nc2CabinetManage['Nc2CabinetManage']['room_id']);
				$nc3RoomId = $nc3Room['Room']['id'];

				Current::write('Room.id', $nc3RoomId);

				$BlocksLanguage->create();
				$Cabinet->create();
				$Block->create();

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				//error_log(print_r($data, true)."\n\n", 3, LOGS."/debug.log");
				if (!$Cabinet->saveCabinet($data)) {
						// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
						// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2CabinetManage) . "\n" .
						var_export($Cabinet->validationErrors, true);
					$this->writeMigrationLog($message);
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2CabinetId = $nc2CabinetManage['Nc2CabinetManage']['cabinet_id'];

				$idMap = [
					$nc2CabinetId => $Cabinet->id
				];
				$this->saveMap('Cabinet', $idMap);
				$Cabinet->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CabinetFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Cabinet->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Cabinet data Migration end.'));
		return true;
	}

/**
 * Save Cabinet File from Nc2.
 *
 * @param array $nc2CabinetFiles Nc2CabinetFile data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3CabinetFileNc2($nc2CabinetFiles) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Cabinet file Migration start.'));

		/* @var $JournalFrameSetting JournalFrameSetting */
		$CabinetFile = ClassRegistry::init('Cabinets.CabinetFile');

		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$CabinetFile->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$CabinetFile->Behaviors->Block->settings = $CabinetFile->actsAs['Blocks.Block'];

		$Nc2CabinetComment = $this->getNc2Model('cabinet_comment');

		foreach ($nc2CabinetFiles as $nc2CabinetFile) {

			$nc2CabinetComment = $Nc2CabinetComment->findByFileId($nc2CabinetFile['Nc2CabinetFile']['file_id'], null, null, -1);
			$CabinetFile->begin();
			try{
				$data = $this->generateNc3CabinetFileData($nc2CabinetFile, $nc2CabinetComment);

				if (!$data) {
					$CabinetFile->rollback();
					continue;
				}

				$Block = ClassRegistry::init('Blocks.Block');

				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);

				$nc3RoomId = $Blocks['Block']['room_id'];

				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				//error_log(print_r($data, true)."\n\n", 3, LOGS."/debug.log");
				if (!$CabinetFile->saveFile($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2CabinetFile) . "\n" .
						var_export($CabinetFile->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2CabinetFileId = $nc2CabinetFile['Nc2CabinetFile']['file_id'];

				$idMap = [
					$nc2CabinetFileId => $CabinetFile->id
				];
				$this->saveMap('CabinetFile', $idMap);
				$CabinetFile->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CabinetFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$CabinetFile->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Cabinet Migration end.'));
		return true;
	}
}