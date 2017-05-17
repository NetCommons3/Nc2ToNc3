<?php
/**
 * Nc2ToNc3Category
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Category
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
 *
 */
class Nc2ToNc3Category extends Nc2ToNc3AppModel {

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
 * Get Nc2Category list data.
 *
 * @param string $nc2TableName Nc2 categor table name.
 * @param array $query Nc2 categor table name.
 * @return array Nc2Category list data.
 */
	public function getNc2CategoryList($nc2TableName, $query) {
		$defaultQuery = [
			'fields' => [
				'category_id',
				'category_name',
			],
			'order' => 'display_sequence',
			'recursive' => -1
		];
		$query = array_merge($defaultQuery, $query);

		$Nc2Category = $this->getNc2Model($nc2TableName);
		$nc2CategoryList = $Nc2Category->find('list', $query);

		return $nc2CategoryList;
	}

/**
 * Generate Nc3Category data.
 *
 * Data sample
 * data[Categories][0][Category][id]:
 * data[Categories][0][CategoryOrder][weight]:1
 * data[Categories][0][CategoriesLanguage][name]:カテゴリ０１
 * data[Categories][1][Category][id]:
 * data[Categories][1][CategoryOrder][weight]:2
 * data[Categories][1][CategoriesLanguage][name]:カテゴリ０１
 *
 * @param array $nc2CategoryList Nc2Category list data with category_id as key and category_name as value.
 * @return array Nc3Category data.
 */
	public function generateNc3CategoryData($nc2CategoryList) {
		// CategoryBehaviorの処理に引っかかるので、idをnullでセットしとく
		// Model::createで初期化した方が良いかも。
		// @see https://github.com/NetCommons3/Categories/blob/3.1.0/Model/Behavior/CategoryBehavior.php#L49-L70
		// @see https://github.com/cakephp/cakephp/blob/2.9.7/lib/Cake/Model/Validator/CakeValidationSet.php#L126
		// @see https://github.com/cakephp/cakephp/blob/2.9.7/lib/Cake/Model/Validator/CakeValidationRule.php#L194

		$weight = 1;
		foreach ($nc2CategoryList as $nc2CategoryName) {
			$data[] = [
				'Category' => [
					'id' => null,
					'block_id' => null,
				],
				'CategoryOrder' => [
					'id' => null,
					'weight' => $weight,
				],
				'CategoriesLanguage' => [
					'id' => null,
					'name' => $nc2CategoryName,
				],
			];
			$weight++;
		}

		return $data;
	}

/**
 * Save category map data.
 *
 * @param array $nc2CategoryList Nc2Category list data with category_id as key and category_name as value.
 * @param string $Nc3BlockId Nc3Block id.
 * @return bool True on success
 */
	public function saveCategoryMap($nc2CategoryList, $Nc3BlockId) {
		/* @var $Block Block */
		$Block = ClassRegistry::init('Blocks.Block');
		$nc3Block = $Block->findById($Nc3BlockId, ['room_id', 'plugin_key'], null, -1);

		/* @var $Category Category */
		$Category = ClassRegistry::init('Categories.Category');
		$nc3Categories = $Category->getCategories($Nc3BlockId, $nc3Block['Block']['room_id']);

		$categoryCount = count($nc2CategoryList);
		//　あり得ないが一応
		if ($categoryCount != count($nc3Categories)) {
			return false;
		}

		$nc2CategoryIds = array_keys($nc2CategoryList);
		$dummyModelName = $this->__getDummyModelName($nc3Block);
		for ($key = 0; $key < $categoryCount; $key++) {
			$idMap = [
				$nc2CategoryIds[$key] => $nc3Categories[$key]['Category']['id']
			];
			$this->saveMap($dummyModelName, $idMap);
		}

		return true;
	}

/**
 * Get Nc3Category id.
 *
 * @param string $Nc3BlockId Nc3Block id.
 * @param string $nc2CategoryId Nc2Category id.
 * @return array Nc3Category id.
 */
	public function getNc3CategoryId($Nc3BlockId, $nc2CategoryId) {
		/* @var $Block Block */
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Block = ClassRegistry::init('Blocks.Block');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$nc3Block = $Block->findById($Nc3BlockId, 'plugin_key', null, -1);
		$dummyModelName = $this->__getDummyModelName($nc3Block);
		$mapIdList = $Nc2ToNc3Map->getMapIdList($dummyModelName, $nc2CategoryId);

		return Hash::get($mapIdList, [$nc2CategoryId]);
	}

/**
 * Get dummy model name.
 *
 * @param array $nc3Block Nc3Block data.
 * @return string Dummy model name.
 */
	private function __getDummyModelName($nc3Block) {
		return Inflector::classify($nc3Block['Block']['plugin_key']) . 'Category';
	}

}
