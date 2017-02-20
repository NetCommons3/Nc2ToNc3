<?php
/**
 * Nc2ToNc3UserUpload
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author      WithOne Company Limited.
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('TemporaryFolder', 'Files.Utility');

/**
 * Nc2ToNc3UserUpload
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 *
 */
class Nc2ToNc3Upload extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Base'];

/**
 *
 * Id map of nc2 and nc3.
 *
 * @var array
 */
	//private $__idMap = null;

/**
 * Update upload file
 *
 * @param string $nc2UploadId Nc2Upload.id.
 * @return array avatar data
 */
	public function updateUploadFile($nc2UploadId) {
		// コマンド実行時アップロードファイルのパスが実行パス配下になるのでとりあえずここでchdir
		// @see https://github.com/NetCommons3/Files/blob/3.0.1/Model/UploadFile.php#L172-L179
		chdir(WWW_ROOT);

		$Nc2Upload = $this->getNc2Model('uploads');
		$nc2Upload = $Nc2Upload->findByUploadId($nc2UploadId, null, null, -1);
		if (!$nc2Upload) {

			$data = [];
			$message = __d('nc2_to_nc3', '%s not found .', 'Nc2Upload:' . $nc2UploadId);
			$this->writeMigrationLog($message);
			return $data;
		}

		$name = $nc2Upload['Nc2Upload']['physical_file_name'];

		$Nc2ToNc3 = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3');
		$tmpName = Hash::get($Nc2ToNc3->data, ['Nc2ToNc3', 'upload_path']) .
		$nc2Upload['Nc2Upload']['file_path'] .
		$name;

		if (!is_readable($tmpName)) {
			$data = [];
			$message = __d('nc2_to_nc3', '%s not found .', 'Nc2Upload upload_id:' . $nc2Upload['Nc2Upload']['upload_id']);
			$this->writeMigrationLog($message);
			return $data;
		}

		// アップロード処理で削除されるので一時フォルダーにコピー
		// @see https://github.com/josegonzalez/cakephp-upload/blob/1.3.1/Model/Behavior/UploadBehavior.php#L337
		$Folder = new TemporaryFolder();
		copy($tmpName, $Folder->path . DS . $name);

		$data = [
			'name' => $nc2Upload['Nc2Upload']['file_name'],
			'type' => $nc2Upload['Nc2Upload']['mimetype'],
			'tmp_name' => $Folder->path . DS . $name,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($tmpName)
		];

		return $data;
	}
}