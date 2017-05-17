<?php
/**
 * Nc2ToNc3ContentComment
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3ContentComment
 *
 * Nc2ToNc3Map.model_nameを重複させないようクラス化した。
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 *
 */
class Nc2ToNc3ContentComment extends Nc2ToNc3AppModel {

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
 * Save category map data.
 *
 * @param array $idMap Id map.
 * @param string $nc3BlockKey Nc3Block key.
 * @return bool True on success
 */
	public function saveContentCommentMap($idMap, $nc3BlockKey) {
		/* @var $Block Block */
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Block = $Block->findByKey($nc3BlockKey, 'plugin_key', null, -1);

		$dummyModelName = $this->__getDummyModelName($nc3Block);
		$this->saveMap($dummyModelName, $idMap);

		return true;
	}

/**
 * Get Nc3ContentComment id.
 *
 * @param string $nc3BlockKey Nc3Block key.
 * @param string $nc2ContentCommentId Nc2ContentComment id.
 * @return array Nc3Category id.
 */
	public function getNc3ContentCommentId($nc3BlockKey, $nc2ContentCommentId) {
		/* @var $Block Block */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Block = ClassRegistry::init('Blocks.Block');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');

		$nc3Block = $Block->findByKey($nc3BlockKey, 'plugin_key', null, -1);
		$dummyModelName = $this->__getDummyModelName($nc3Block);
		$mapIdList = $Nc2ToNc3Map->getMapIdList($dummyModelName, $nc2ContentCommentId);

		return Hash::get($mapIdList, [$nc2ContentCommentId]);
	}

/**
 * Get dummy model name.
 *
 * @param array $nc3Block Nc3Block data.
 * @return string Dummy model name.
 */
	private function __getDummyModelName($nc3Block) {
		return Inflector::classify($nc3Block['Block']['plugin_key']) . 'ContentComment';
	}

}