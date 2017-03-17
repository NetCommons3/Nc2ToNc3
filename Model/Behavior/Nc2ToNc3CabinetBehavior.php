<?php
/**
 * Nc2ToNc3CabinetBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3CabinetBehavior
 *
 */
class Nc2ToNc3CabinetBehavior extends Nc2ToNc3BaseBehavior
{

	/**
	 * Get Log argument.
	 *
	 * @param Model $model Model using this behavior.
	 * @param array $nc2CabinetBlock Array data
	 * @return string Log argument
	 */
	public function getLogArgument(Model $model, $nc2CabinetBlock)
	{
		return $this->__getLogArgument($nc2CabinetBlock);
	}

	/**
	 * Generate generateNc3Cabinet Data.
	 *
	 * Data sample
	 * data[CabinetFrameSetting][id]:
	 * data[CabinetFrameSetting][frame_key]:
	 * data[CabinetFrameSetting][room_id]:1
	 * data[CabinetFrameSetting][articles_per_page]:0
	 * data[CabinetFrameSetting][created]:
	 * data[CabinetFrameSetting][created-user]:
	 * data[Cabinet][id]:
	 * data[Cabinet][key]:
	 * data[Cabinet][name]:
	 *
	 * @param Model $model Model using this behavior.
	 * @param array $nc2CabinetBlock Nc2CabinetBlock data.
	 * @param array $nc2Journal Nc2Journal data.
	 * @return array Nc3Cabinet data.
	 */

	public function generateNc3CabinetData(Model $model, $nc2CabinetManage, $nc2CabinetBlock)
	{
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2CabinetBlock['Nc2CabinetBlock']['block_id'];

		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
		//	$message = __d('nc2_to_nc3', '%s does not migration.', $this->_getLogArgument($nc2CabinetBlock));
		//	$this->_writeMigrationLog($message);
			return [];
		}

		$nc2CabinetId = $nc2CabinetManage['Nc2CabinetManage']['cabinet_id'];

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Cabinet', $nc2CabinetId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}
		//$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$data = [
			'Frame' => [
				'id' => $frameMap['Frame']['id']
			],
			'Block' => [
				'id' => '',
				'plugin_key' => 'cabinets',
				'name' => $nc2CabinetManage['Nc2CabinetManage']['cabinet_name'],
				'public_type' => $nc2CabinetManage['Nc2CabinetManage']['active_flag'],
				'publish_start' => '',
				'publish_end' => ''
			],
			'Cabinet' => [
				'id' => '',
				'key' => '',
				'name' => $nc2CabinetManage['Nc2CabinetManage']['cabinet_name'],
			],
			'CabinetSetting' => [
				'use_workflow' => '1',
			],
			'BlocksLanguage' => [
				'language_id' => '',
				'name' => $nc2CabinetManage['Nc2CabinetManage']['cabinet_name']
			],
			'CabinetFile' => [
				'status' => '1'
			],
			'_NetCommonsTime' => [
				'user_timezone' => 'Asia/Tokyo',
				'convert_fields' => 'Block.publish_start,Block.publish_end'
			]
		];
		return $data;
	}





	public function generateNc3CabinetFileData(Model $model, $nc2CabinetFile, $nc2CabinetComment)
	{
		$nc2CabinetFileId = $nc2CabinetFile['Nc2CabinetFile']['file_id'];
		$nc2CabinetFileCabinetId = $nc2CabinetFile['Nc2CabinetFile']['cabinet_id'];

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		//すでに移動したファイルは移行しないようにする
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CabinetFile', $nc2CabinetFileId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		//キャビネットの枠は、generateNc3CabinetDataで移行したはずなので、移行したキャビネット枠の情報を取得
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Cabinet', $nc2CabinetFileCabinetId);

		$nc3CabinetId = $mapIdList[$nc2CabinetFileCabinetId];

		$Cabinet = ClassRegistry::init('Cabinets.Cabinet');
		$Cabinets = $Cabinet->findById($nc3CabinetId, null, null, -1);

		//NC2のparent_idを取得し、なければ、NC2-cabinet_file.cabinet_idに対応するNC3-cabinet.keyを取得し、
		//NC3-cabinet_file_trees.parent_id = NULL AND NC3-cabinet_file_trees.cabinet_key = 取得したNC3-cabinet.key
		//という条件で取得したNC3-cabinet_file_trees.idをNC3-cabinet_file_trees.parentにセット。
		//あれば、NC2-cabinet_file.parent_idに対応するNC3-cabinet_files.idをNC3-cabinet_file_trees.parentにセット
		$nc2CabinetFileParentId = $nc2CabinetFile['Nc2CabinetFile']['parent_id'];

		if (!$nc2CabinetFileParentId) {
			//$Cabinets = $nc2Cabinet->findByKey($nc2CabinetFile['Nc2CabinetFile']['cabinet_key'], null, null, -1);
			$nc3CabinetKey = $Cabinets['Cabinet']['key'];
			$CabinetFileTree = ClassRegistry::init('Cabinets.CabinetFileTree');
			$nc3CabinetFileTrees = $CabinetFileTree->findByCabinetKeyAndParentId($nc3CabinetKey, '', null, null, -1);
			$nc3CabinetFileTreeId = $nc3CabinetFileTrees['CabinetFileTree']['id'];
		} else {
			$mapIdList = $Nc2ToNc3Map->getMapIdList('CabinetFile', $nc2CabinetFileParentId);
			$nc3CabinetFileTreeId = $mapIdList[$nc2CabinetFileParentId];
		}

		//ファイルの場合は、ファイルアップロードの準備
		if (!$nc2CabinetFile['Nc2CabinetFile']['file_type']) {
			$nc2UploadId = $nc2CabinetFile['Nc2CabinetFile']['upload_id'];
			$nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
			$nc3CabinetFile = $nc2ToNc3Upload->generateUploadFile($nc2UploadId);

			$data = [
				'Block' => [
					'id' => $Cabinets['Cabinet']['block_id']
				],
				'CabinetFile' => [
					'key' => '',
					'is_folder' => $nc2CabinetFile['Nc2CabinetFile']['file_type'],
					'filename' => $nc2CabinetFile['Nc2CabinetFile']['file_name'],
					'description' => $nc2CabinetComment['Nc2CabinetComment']['comment'],
					'status' => '1',
					'cabinet_key' => $Cabinets['Cabinet']['key'],
					'file' => $nc3CabinetFile
				],
				'CabinetFileTree' => [
					'parent_id' => $nc3CabinetFileTreeId
				]
			];
			//フォルダの場合
		} else {
			$data = [
				'Block' => [
					'id' => $Cabinets['Cabinet']['block_id']
				],
				'CabinetFile' => [
					'key' => '',
					'is_folder' => $nc2CabinetFile['Nc2CabinetFile']['file_type'],
					'filename' => $nc2CabinetFile['Nc2CabinetFile']['file_name'],
					'description' => $nc2CabinetComment['Nc2CabinetComment']['comment'],
					'status' => '1',
					'cabinet_key' => $Cabinets['Cabinet']['key'],
				],
				'CabinetFileTree' => [
					'parent_id' => $nc3CabinetFileTreeId
				]
			];
		}
		return $data;
	}
}