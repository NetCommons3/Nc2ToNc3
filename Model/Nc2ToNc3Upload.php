<?php
/**
 * Nc2ToNc3UserRole
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3UserRole
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
	private $__idMap = null;

/**
 * Update upload file
 *
 * @param string $upload_id Nc2Upload.id.
 * @return bool True on success.
 */
public function updateUploadFile($nc2UploadId)
{
	$Nc2Upload = $this->getNc2Model('uploads');

	//$options = array( 'Nc2Upload.upload_id' => $nc2UploadId);
	//$nc2UploadIdInt=intval($nc2UploadId);

	$query = [
		'fields' => [
			'Nc2Upload.upload_id',
			'Nc2Upload.file_name',
			'Nc2Upload.physical_file_name',
			'Nc2Upload.mimetype',
			'Nc2Upload.file_size',
		],
		'conditions' => [
			'Nc2Upload.upload_id' => $nc2UploadId
		],
		'recursive' => -1
		];

	$nc2Upload = $Nc2Upload->find('all', $query);

	return $nc2Upload;

}
}