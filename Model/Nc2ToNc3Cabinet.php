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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Cabinet',
		'Nc2ToNc3.Nc2ToNc3BlockRolePermission',
	];

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

		/* @var $Nc2TodoBlock AppModel */
		$Nc2CabinetBlock = $this->getNc2Model('cabinet_block');
		$nc2CabinetBlocks = $Nc2CabinetBlock->find('all');
		if (!$this->__saveFrameFromNc2($nc2CabinetBlocks)) {
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
		$CabinetSetting = ClassRegistry::init('Cabinets.CabinetSetting');

		foreach ($nc2CabinetManages as $nc2CabinetManage) {

			$Nc2CabinetBlock = $this->getNc2Model('cabinet_block');
			//$nc2CabinetBlock = $Nc2CabinetBlock->find('all');

			$nc2CabinetBlock = $Nc2CabinetBlock->findByRoomId($nc2CabinetManage['Nc2CabinetManage']['room_id'], null, null, -1);
			if (!$nc2CabinetBlock) {
				$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2CabinetManage));
				$this->writeMigrationLog($message);
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
				$CabinetSetting->create();

				Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				//error_log(print_r($data, true)."\n\n", 3, LOGS."/debug.log");
				if (!$Cabinet->saveCabinet($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Cabinet->rollback();

						// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
						// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2CabinetManage) . "\n" .
						var_export($Cabinet->validationErrors, true);
					$this->writeMigrationLog($message);
					$Cabinet->rollback();
					continue;
				}

				$cabinet = $Cabinet->findById($Cabinet->id, 'block_id', null, -1);
				$block = $Block->findById($cabinet['Cabinet']['block_id'], null, null, -1);
				Current::write('Block', $block['Block']);
				foreach ($data['BlockRolePermission'] as &$permission) {
					foreach ($permission as &$role) {
						$role['block_key'] = $block['Block']['key'];
					}
				}

				if (!$CabinetSetting->saveCabinetSetting($data)) {
					$message = $this->getLogArgument($data) . "\n" .
						var_export($CabinetSetting->validationErrors, true);
					$this->writeMigrationLog($message);
					$Cabinet->rollback();

					continue;
				}

				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

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
			Current::remove('Block');
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

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		$UploadFile = ClassRegistry::init('Files.UploadFile');

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
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block.id', $Blocks['Block']['id']);
				$nc3RoomId = $Blocks['Block']['room_id'];
				Current::write('Plugin.key', 'cabinets');
				Current::write('Room.id', $nc3RoomId);
				Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$CabinetFile->create();
				$Block->create();
				$Topic->create();
				if (!($nc3CabinetFile = $CabinetFile->saveFile($data))) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2CabinetFile) . "\n" .
						var_export($CabinetFile->validationErrors, true);
					$this->writeMigrationLog($message);
					$CabinetFile->rollback();
					continue;
				}
				$downloadCount = intval($data['DownloadCount'] ?? 0);
				if ($downloadCount) {
					// DownloadCount更新
					$file = $UploadFile->find('first', [
						'conditions' => [
							'plugin_key' => 'cabinets',
							'content_key' => $nc3CabinetFile['CabinetFile']['key'],
							'field_name' => 'file',
						]
					]);
					$file['UploadFile']['download_count'] = $downloadCount;
					$file['UploadFile']['total_download_count'] = $downloadCount;
					$UploadFile->create();
					$UploadFile->save($file, false, false);
				}

				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

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
		Current::remove('Block.id');
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Cabinet Migration end.'));
		return true;
	}

/**
 * Save Frame from Nc2.
 *
 * @param array $nc2CabinetBlocks Nc2CabinetBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveFrameFromNc2($nc2CabinetBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Frame data Migration start.'));

		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		/* @var $Frame Frame */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Cabinet Cabinet */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Frame = ClassRegistry::init('Frames.Frame');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Cabinet = ClassRegistry::init('Cabinets.Cabinet');
		foreach ($nc2CabinetBlocks as $nc2CabinetBlock) {
			$Frame->begin();
			try {
				$nc2BlockId = $nc2CabinetBlock['Nc2CabinetBlock']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				if (!$frameMap) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2CabinetBlock));
					$this->writeMigrationLog($message);

					$Frame->rollback();
					continue;
				}

				// CabinetFrameModelは存在しないが、移行済みデータのためにDummyModel名で取得
				$mapIdList = $Nc2ToNc3Map->getMapIdList('CabinetFrame', $nc2BlockId);
				if ($mapIdList) {
					$Frame->rollback();	// 移行済み
					continue;
				}

				$nc2CabinetId = $nc2CabinetBlock['Nc2CabinetBlock']['cabinet_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Cabinet', $nc2CabinetId);
				$nc3CabinetId = Hash::get($mapIdList, [$nc2CabinetId]);
				$nc3Cabinet = $Cabinet->findById($nc3CabinetId, ['block_id'], null, -1);
				if (!$nc3Cabinet) {
					$Frame->rollback();	// ブロックデータなし
					continue;
				}

				$data['Frame'] = [
					'id' => $frameMap['Frame']['id'],
					'plugin_key' => 'cabinets',
					'block_id' => $nc3Cabinet['Cabinet']['block_id'],
				];
				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。 var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2CabinetBlock) . "\n" .
						var_export($Frame->validationErrors, true);
					$this->writeMigrationLog($message);

					$Frame->rollback();
					continue;
				}

				$idMap = [
					$nc2BlockId => $frameMap['Frame']['id'],
				];
				// TaskFrameModelは存在しないが、移行済みデータのためにDummyModel名で登録
				$this->saveMap('CabinetFrame', $idMap);

				$Frame->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $QuestionnaireFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Frame->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Frame data Migration end.'));

		return true;
	}

}
