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
class Nc2ToNc3CabinetBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Cabinet Array data
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Cabinet) {
		return $this->__getLogArgument($nc2Cabinet);
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
 * @param array $nc2CabinetManage Nc2CabinetBlock data.
 * @param array $nc2CabinetBlock Nc2CabinetBlock data.
 * @return array Nc3Cabinet data.
 */

	public function generateNc3CabinetData(Model $model, $nc2CabinetManage, $nc2CabinetBlock) {
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2CabinetBlock['Nc2CabinetBlock']['block_id'];

		$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
		if (!$frameMap) {
			$message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2CabinetBlock));
			$this->_writeMigrationLog($message);
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
		$data = [];

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

/**
 * Generate generateNc3Cabinet Data.
 *
 * Data sample
 * data[Block][id]:
 * data[CabinetFile][is_folder]:
 * data[CabinetFile][filename]:
 * data[CabinetFile][description]:
 * data[CabinetFile][status]:
 * data[CabinetFile][cabinet_key]:
 * data[CabinetFile][created_user]:
 * data[CabinetFile][created]:
 * data[CabinetFile][file]:
 * data[CabinetFileTree][parent_id]:
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2CabinetFile Nc2CabinetFile data.
 * @param array $nc2CabinetComment Nc2CabinetComment data.
 * @return array Nc3Cabinet File data.
 *
 */

	public function generateNc3CabinetFileData(Model $model, $nc2CabinetFile, $nc2CabinetComment) {
		$nc2CabinetFileId = $nc2CabinetFile['Nc2CabinetFile']['file_id'];
		$nc2CabFileCabinetId = $nc2CabinetFile['Nc2CabinetFile']['cabinet_id'];

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		//すでに移動したファイルは移行しないようにする
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('CabinetFile', $nc2CabinetFileId);
		if ($mapIdList) {
			// 移行済み
			return [];
		}

		//キャビネットの枠は、generateNc3CabinetDataで移行したはずなので、移行したキャビネット枠の情報を取得
		$mapIdList = $Nc2ToNc3Map->getMapIdList('Cabinet', $nc2CabFileCabinetId);
		if (!$mapIdList) {
			return [];
		}

		$nc3CabinetId = $mapIdList[$nc2CabFileCabinetId];

		$Cabinet = ClassRegistry::init('Cabinets.Cabinet');
		$Cabinets = $Cabinet->findById($nc3CabinetId, null, null, -1);

		//NC2のparent_idを取得し、なければ、NC2-cabinet_file.cabinet_idに対応するNC3-cabinet.keyを取得し、
		//NC3-cabinet_file_trees.parent_id = NULL AND NC3-cabinet_file_trees.cabinet_key = 取得したNC3-cabinet.key
		//という条件で取得したNC3-cabinet_file_trees.idをNC3-cabinet_file_trees.parentにセット。
		//あれば、NC2-cabinet_file.parent_idに対応するNC3-cabinet_files.idをNC3-cabinet_file_trees.parentにセット
		$nc2CabFileParentId = $nc2CabinetFile['Nc2CabinetFile']['parent_id'];

		if (!$nc2CabFileParentId) {
			//$Cabinets = $nc2Cabinet->findByKey($nc2CabinetFile['Nc2CabinetFile']['cabinet_key'], null, null, -1);
			$nc3CabinetKey = $Cabinets['Cabinet']['key'];
			$CabinetFileTree = ClassRegistry::init('Cabinets.CabinetFileTree');
			$nc3CabinetFileTrees = $CabinetFileTree->findByCabinetKeyAndParentId($nc3CabinetKey, '', null, null, -1);
			$nc3CabinetFileTreeId = $nc3CabinetFileTrees['CabinetFileTree']['id'];
		} else {
			$mapIdList = $Nc2ToNc3Map->getMapIdList('CabinetFile', $nc2CabFileParentId);
			/* @var $CabinetFile CabinetFile */
			$CabinetFile = ClassRegistry::init('Cabinets.CabinetFile');
			$nc3CabinetFile = $CabinetFile->findById(
				$mapIdList[$nc2CabFileParentId],
				'cabinet_file_tree_id',
				null,
				-1
			);
			$nc3CabinetFileTreeId = $nc3CabinetFile['CabinetFile']['cabinet_file_tree_id'];
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		//ファイルの場合は、ファイルアップロードの準備
		if (!$nc2CabinetFile['Nc2CabinetFile']['file_type']) {
			$nc2UploadId = $nc2CabinetFile['Nc2CabinetFile']['upload_id'];
			$nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
			$nc3CabinetFile = $nc2ToNc3Upload->generateUploadFile($nc2UploadId);

			$data['CabinetFile'] = [
				'key' => '',
				'is_folder' => $nc2CabinetFile['Nc2CabinetFile']['file_type'],
				'filename' => $nc2CabinetFile['Nc2CabinetFile']['file_name'] . '.' . $nc2CabinetFile['Nc2CabinetFile']['extension'],
				'description' => $nc2CabinetComment['Nc2CabinetComment']['comment'],
				'status' => '1',
				'cabinet_key' => $Cabinets['Cabinet']['key'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CabinetFile['Nc2CabinetFile']),
				'created' => $this->_convertDate($nc2CabinetFile['Nc2CabinetFile']['insert_time']),
				'file' => $nc3CabinetFile,
				// 新着用に更新日を移行
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L146
				'modified' => $this->_convertDate($nc2CabinetFile['Nc2CabinetFile']['update_time']),
			];
			//フォルダの場合
		} else {
			$data['CabinetFile'] = [
				'key' => '',
				'is_folder' => $nc2CabinetFile['Nc2CabinetFile']['file_type'],
				'filename' => $nc2CabinetFile['Nc2CabinetFile']['file_name'],
				'description' => $nc2CabinetComment['Nc2CabinetComment']['comment'],
				'status' => '1',
				'cabinet_key' => $Cabinets['Cabinet']['key'],
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2CabinetFile['Nc2CabinetFile']),
				'created' => $this->_convertDate($nc2CabinetFile['Nc2CabinetFile']['insert_time']),
				// 新着用に更新日を移行
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L146
				'modified' => $this->_convertDate($nc2CabinetFile['Nc2CabinetFile']['update_time']),
			];
		}
		$data['Block'] = [
			'id' => $Cabinets['Cabinet']['block_id']
		];
		$data['CabinetFileTree'] = [
			'parent_id' => $nc3CabinetFileTreeId
		];

		return $data;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Cabinet Array data of $nc2CabinetBlock, Nc2CabinetManage, Nc2CabinetFile.
 * @return string Log argument
 */
	private function __getLogArgument($nc2Cabinet) {
		if (isset($nc2Cabinet['Nc2CabinetBlock'])) {
			return 'CabinetBlock ' .
				'block_id:' . $nc2Cabinet['CabinetBlock']['block_id'];
		}

		if (isset($nc2Cabinet['Nc2CabinetManage'])) {
			return 'CabinetManage ' .
				'cabinet_id:' . $nc2Cabinet['CabinetManage']['cabinet_id'];
		}

		if (isset($nc2Cabinet['Nc2CabinetFile'])) {
			return 'Nc2CabinetFile ' .
				'file_id:' . $nc2Cabinet['Nc2CabinetFile']['file_id'];
		}
	}
}