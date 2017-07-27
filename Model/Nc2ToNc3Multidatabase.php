<?php
/**
 * Nc2ToNc3Blog
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Blog
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
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)]
 *
 */
class Nc2ToNc3Multidatabase extends Nc2ToNc3AppModel {

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
		'Nc2ToNc3.Nc2ToNc3Multidatabase',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Multidatabase Migration start.'));

		/* @var $Nc2Blog AppModel */
		$Nc2Multidatabase = $this->getNc2Model('multidatabase');
		$nc2Multidatabases = $Nc2Multidatabase->find('all');
		if (!$this->__saveNc3MultidatabaseFromNc2($nc2Multidatabases)) {
			return false;
		}

		/* @var $Nc2MultidatabaseBlock AppModel */
		$Nc2MultidatabaseBlock = $this->getNc2Model('multidatabase_block');
		$nc2MultidatabaseBlocks = $Nc2MultidatabaseBlock->find('all');
		if (!$this->__saveNc3MultidatabaseFrameSettingFromNc2($nc2MultidatabaseBlocks)) {
			return false;
		}

		$Nc2MultidatabaseMetadata = $this->getNc2Model('multidatabase_metadata');
		// col_noをうめるためにmultidatabase_id
		$nc2MultidatabaseMetadatas = $Nc2MultidatabaseMetadata->find('all', [
			'order' => 'multidatabase_id ASC'
		]);
		if (!$this->__saveNc3MultidatabaseMetadataFromNc2($nc2MultidatabaseMetadatas)) {
			return false;
		}

		$Nc2MultidbContent = $this->getNc2Model('multidatabase_content');
		$nc2MultidbContents = $Nc2MultidbContent->find('all');
		if (!$this->__saveNc3MultidbContentFromNc2($nc2MultidbContents)) {
			return false;
		}


		// Comment
		$Nc2MultidbComment = $this->getNc2Model('multidatabase_comment');
		$nc2MultidbComments = $Nc2MultidbComment->find('all');
		if (!$this->__saveNc3ContentCommentFromNc2($nc2MultidbComments)) {
			return false;
		}

		// insert MetadataSettings
		$Metadata = ClassRegistry::init('Multidatabases.MultidatabaseMetadata');
		$metadata = $Metadata->find('all', [
			'conditions' => [
				'type' => 'autonumber'
			]
		]);
		$MetadataContent = ClassRegistry::init('Multidatabases.MultidatabaseContent');
		$MetadataSetting = ClassRegistry::init('Multidatabases.MultidatabaseMetadataSetting');
		//$MetadataContent->virtualFields['number'] = 0;
		foreach($metadata as $metadatum) {
			$colNo = $metadatum['MultidatabaseMetadata']['col_no'];
			$result = $MetadataContent->find('first', [
				'conditions' => [
					'multidatabase_id' => $metadatum['MultidatabaseMetadata']['multidatabase_id'],

				],
				'fields' => [
					'MAX(cast(value' . $colNo . ' as UNSIGNED)) AS number'
				]
			]);
			if ($result) {
				$number = $result[0]['number'];
				if ($number === null) {
					$number = 0;
				}
			}else{
				$number = 0;
			}

			$setting = [
				'MultidatabaseMetadataSetting' => [
					'id' => $metadatum['MultidatabaseMetadata']['id'],
					'auto_number_sequence' => $number
				]
			];
			$MetadataSetting->create();
			$MetadataSetting->save($setting);
		}
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Multidatabase Migration end.'));
		return true;
	}

/**
 * Save JournalFrameSetting from Nc2.
 *
 * @param array $nc2Multidatabases Nc2Journal data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3MultidatabaseFromNc2($nc2Multidatabases) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Multidatabase data Migration start.'));

		/* @var $Multidatabase Blog */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$Multidatabase = ClassRegistry::init('Multidatabases.Multidatabase');

		Current::write('Plugin.key', 'multidatabases');
		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Multidatabase->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Multidatabase->Behaviors->Block->settings = $Multidatabase->actsAs['Blocks.Block'];

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		//$Topic = ClassRegistry::init('Topics.Topic');

		$Metadata = ClassRegistry::init('Multidatabases.MultidatabaseMetadata');


		foreach ($nc2Multidatabases as $nc2Multidatabase) {
			$Multidatabase->begin();
			try {
				$data = $this->generateNc3MultidatabaseData($nc2Multidatabase);
				if (!$data) {
					$Multidatabase->rollback();
					continue;
				}

				// いる？
				$nc3RoomId = $data['Block']['room_id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$Multidatabase->create();
				$Block->create();
				//$Topic->create();

				if (!$Multidatabase->saveMultidatabase($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$Multidatabase->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Multidatabase) . "\n" .
						var_export($Multidatabase->validationErrors, true);
					$this->writeMigrationLog($message);
					$Multidatabase->rollback();
					continue;
				}


				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2MultidatabaseId = $nc2Multidatabase['Nc2Multidatabase']['multidatabase_id'];
				$idMap = [
					$nc2MultidatabaseId => $Multidatabase->id
				];
				$this->saveMap('Multidatabase', $idMap);

				// ダミーでSaveしたmetadataレコードの削除
				$savedData = $Multidatabase->findById($Multidatabase->id);
				$key =  $savedData['Multidatabase']['key'];
				$Metadata->deleteAll(['key' => $key], false, false);

				$Multidatabase->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Multidatabase->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Blog Multidatabase Migration end.'));
		return true;
	}

/**
 * Save BlogFrameSetting from Nc2.
 *
 * @param array $nc2MultidatabaseBlocks Nc2ournalBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3MultidatabaseFrameSettingFromNc2($nc2MultidatabaseBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  MultidatabaseFrameSetting data Migration start.'));

		/* @var $MultidbFrameSetting BlogFrameSetting */
		/* @var $Frame Frame */
		$MultidbFrameSetting = ClassRegistry::init('Multidatabases.MultidatabaseFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2MultidatabaseBlocks as $nc2MultidatabaseBlock) {
			$MultidbFrameSetting->begin();
			try {
				$data = $this->generateNc3MultidatabaseFrameSettingData($nc2MultidatabaseBlock);
				if (!$data) {
					$MultidbFrameSetting->rollback();
					continue;
				}

				$MultidbFrameSetting->create();
				if (!$MultidbFrameSetting->saveMultidatabaseFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2MultidatabaseBlock) . "\n" .
						var_export($MultidbFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$MultidbFrameSetting->rollback();
					continue;
				}

				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2MultidatabaseBlock) . "\n" .
						var_export($MultidbFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$MultidbFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2MultidatabaseBlock['Nc2MultidatabaseBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $MultidbFrameSetting->id
				];
				$this->saveMap('MultidatabaseFrameSetting', $idMap);

				$MultidbFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::saveBlogFrameSetting()でthrowされるとこの処理に入ってこない
				$MultidbFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		/*
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');
		*/

		$this->writeMigrationLog(__d('nc2_to_nc3', '  MultidatabaseFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save Metadata from Nc2.
 *
 * @param array $nc2Metadata Nc2ournalBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3MultidatabaseMetadataFromNc2($nc2Metadata) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  MultidatabaseMetadata Migration start.'));

		/* @var $MultidbMetadata BlogFrameSetting */
		/* @var $Frame Frame */
		$MultidbMetadata = ClassRegistry::init('Multidatabases.MultidatabaseMetadata');

		$MultidbMetadata->Behaviors->load('NetCommons.OriginalKey');

		$currentDatabaseId = 0;
		foreach ($nc2Metadata as $nc2Metadatum) {
			if ($currentDatabaseId != $nc2Metadatum['Nc2MultidatabaseMetadata']['multidatabase_id']) {
				$this->varCharColNo = 1;
				$this->textColNo = 80;
				$currentDatabaseId = $nc2Metadatum['Nc2MultidatabaseMetadata']['multidatabase_id'];
			}
			$MultidbMetadata->begin();
			try {
				$data = $this->generateNc3MultidatabaseMetadata($nc2Metadatum);
				if (!$data) {
					$MultidbMetadata->rollback();
					continue;
				}

				$MultidbMetadata->create();
				if (!$MultidbMetadata->save($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Metadatum) . "\n" .
						var_export($MultidbMetadata->validationErrors, true);
					$this->writeMigrationLog($message);

					$MultidbMetadata->rollback();
					continue;
				}

				$nc2BlockId = $nc2Metadatum['Nc2MultidatabaseMetadata']['metadata_id'];
				$idMap = [
					$nc2BlockId => $MultidbMetadata->id
				];
				$this->saveMap('MultidatabaseMetadata', $idMap);

				$MultidbMetadata->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::saveBlogFrameSetting()でthrowされるとこの処理に入ってこない
				$MultidbMetadata->rollback($ex);
				throw $ex;
			}
		}

		/*
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');
		*/

		$this->writeMigrationLog(__d('nc2_to_nc3', '  MultidatabaseMetadata Migration end.'));

		return true;
	}

/**
 * Save BlogEntry from Nc2.
 *
 * @param array $nc2MultidbContents Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3MultidbContentFromNc2($nc2MultidbContents) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Multidatabase Content Migration start.'));

		/* @var $DbContent BlogEntry */
		/* @var $Nc2ToNc3Category Nc2ToNc3Category */
		$DbContent = ClassRegistry::init('Multidatabases.MultidatabaseContent');

		Current::write('Plugin.key', 'multidatabases');

		//$Nc2Journal = $this->getNc2Model('journal');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		//$Topic = ClassRegistry::init('Topics.Topic');

		$Nc2MultidbFile = $this->getNc2Model('multidatabase_file');

		$AuthorizationKey = ClassRegistry::init('AuthorizationKeys.AuthorizationKey');
		$UploadFile = ClassRegistry::init('Files.UploadFile');

		$Like = ClassRegistry::init('Likes.Like');
		foreach ($nc2MultidbContents as $nc2MultidbContent) {
			$DbContent->begin();
			//$DbContent->Behaviors->disable('Attachment');

			try {
				$data = $this->generateNc3MultidbContent($nc2MultidbContent);
				if (!$data) {
					$DbContent->rollback();
					continue;
				}

				$nc3BlockId = $data['Block']['id'];

				$Block = ClassRegistry::init('Blocks.Block');
				$block = $Block->findById($nc3BlockId, null, null, -1);
				$nc3RoomId = $block['Block']['room_id'];

				$data['Block'] = $block['Block'];
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block', $block['Block']);
				Current::write('Room.id', $nc3RoomId);
				//
				//$BlocksLanguage->create();
				$DbContent->create();
				//$Block->create();
				//$Topic->create();

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				$nc3Status = $data['MultidatabaseContent']['status'];
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = ($nc3Status != 2);

				// Hash::merge で BlogEntry::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Blogs/blob/3.1.0/Model/BlogEntry.php#L138-L141
				$DbContent->validate = [];

				//if (!$DbContent->saveContent($data, false)) {
				if (!$DbContent->save($data, false)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$DbContent->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2MultidbContent) . "\n" .
						var_export($DbContent->validationErrors, true);
					$this->writeMigrationLog($message);
					$DbContent->rollback();
					continue;
				}

				// ここでファイルパスワード保存
				if (isset($data['AuthorizationKey'])) {
					foreach($data['AuthorizationKey'] as $authKey) {
						if (! $AuthorizationKey->saveAuthorizationKey(
							'MultidatabaseContent',
							$DbContent->id,
							$authKey['authorization_key'],
							$authKey['additional_id']
						)) {
							throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
						}
					}
				}

				$nc3DbContent = $DbContent->findById($DbContent->id, ['key'], null, -1);

				// DownloadCount更新
				if (isset($data['DownloadCount'])) {
					foreach ($data['DownloadCount'] as $fieldName => $count) {
						$file = $UploadFile->find('first', [
							'conditions' => [
								'plugin_key' => 'multidatabases',
								'content_key' => $nc3DbContent['MultidatabaseContent']['key'],
								'field_name' => $fieldName . '_attach',
							]
						]);
						$file['UploadFile']['download_count'] = $count;
						$file['UploadFile']['total_download_count'] = $count;
						$UploadFile->create();
						$UploadFile->save($file, false, false);
					}
				}

				// Like
				if ($data['Like']['like_count']) {
					$data['Like']['content_key'] = $nc3DbContent['MultidatabaseContent']['key'];
					$Like->create();
					$Like->save($data);
				}

				//unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2MultidbContent['Nc2MultidatabaseContent']['content_id'];
				$idMap = [
					$nc2PostId => $DbContent->id
				];
				$this->saveMap('MultidatabaseContent', $idMap);
				$DbContent->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BlogFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$DbContent->rollback($ex);
				throw $ex;
			}

			Current::remove('Block.id');
			Current::remove('Room.id');
		}

		//Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Multidatabase Content Migration end.'));
		return true;
	}

/**
 * Save ContentComment from Nc2.
 *
 * @param array $nc2MultidbComments Nc2JournalPost data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3ContentCommentFromNc2($nc2MultidbComments) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Content Comment data Migration start.'));

		/* @var $ContentComment ContentComment */
		/* @var $Nc2ToNc3Comment Nc2ToNc3ContentComment */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$ContentComment = ClassRegistry::init('ContentComments.ContentComment');
		$Nc2ToNc3Comment = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3ContentComment');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		foreach ($nc2MultidbComments as $nc2MultidbComment) {
			$ContentComment->begin();
			try {
				$data = $this->generateNc3ContentCommentData($nc2MultidbComment);
				if (!$data) {
					$ContentComment->rollback();
					continue;
				}

				$nc2RoomId = $nc2MultidbComment['Nc2MultidatabaseComment']['room_id'];
				$mapIdList = $Nc2ToNc3Map->getMapIdList('Room', $nc2RoomId);
				$nc3RoomId = $mapIdList[$nc2RoomId];
				$nc3Status = $data['ContentComment']['status'];

				// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = ($nc3Status != 2);

				$ContentComment->create();
				// 一応Model::validatの初期化
				$ContentComment->validate = [];

				if (!$ContentComment->saveContentComment($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2MultidbComment) . "\n" .
						var_export($ContentComment->validationErrors, true);
					$this->writeMigrationLog($message);

					$ContentComment->rollback();
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2MultidbComment['Nc2MultidatabaseComment']['comment_id'];
				$idMap = [
					$nc2PostId => $ContentComment->id
				];
				if (!$Nc2ToNc3Comment->saveContentCommentMap($idMap, $data['ContentComment']['block_key'])) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2MultidbComment);
					$this->writeMigrationLog($message);

					$ContentComment->rollback();
					continue;
				}

				$ContentComment->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				$ContentComment->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Content Comment Migration end.'));

		return true;
	}

}