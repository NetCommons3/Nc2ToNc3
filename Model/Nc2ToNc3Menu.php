<?php
/**
 * Nc2ToNc3Menu
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Menu
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
 * @see Nc2ToNc3MenuBehavior
 * @method string getLogArgument($nc2MenuDetail)
 * @method array generateNc3MenuFrameSettingData($nc2MenuDetail)
 * @method array generateNc3MenuFramePageOrRoomData($nc2MenuDetail, $nc3MenuFrameSetting)
 * @method array addOtherRoomAndPageData($nc3MenuFrameSetting)
 *
 */
class Nc2ToNc3Menu extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Menu'];

/**
 * Migration method.
 *
 * @return bool True on success.
 * @throws Exception
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Menu Migration start.'));

		// Nc2MenuDetail.block_idごとにMenuFrameSetting::saveMenuFrameSettingを呼び出すので
		// 全件取得してループするやり方だと、if文がちょっと分かりづらくなる。（Nc3MenuFrameSettingデータを作る条件や、対応するFrameがない場合など）
		// なので、Nc2MenuDetail.block_id単位で処理する
		/* @var $Nc2MenuDetail AppModel */
		$Nc2MenuDetail = $this->getNc2Model('menu_detail');
		$query = [
			'fields' => 'DISTINCT block_id',
			'recursive' => -1
		];
		$nc2MenuDetails = $Nc2MenuDetail->find('all', $query);
		foreach ($nc2MenuDetails as $nc2MenuDetail) {
			if (!$this->__saveMenuFrameSettingFromNc2($nc2MenuDetail['Nc2MenuDetail']['block_id'])) {
				return false;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Menu Migration end.'));

		return true;
	}

/**
 * Save MenuFrameSetting from Nc2.
 *
 * @param string $nc2BlockId Nc2MenuDetail block_id.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveMenuFrameSettingFromNc2($nc2BlockId) {
		/* @var $Nc2MenuDetail AppModel */
		/* @var $MenuFrameSetting MenuFrameSetting */
		$Nc2MenuDetail = $this->getNc2Model('menu_detail');
		$MenuFrameSetting = ClassRegistry::init('Menus.MenuFrameSetting');

		$query = [
			'conditions' => [
				'Nc2MenuDetail.block_id' => $nc2BlockId
			],
			'recursive' => -1
		];
		$nc2MenuDetails = $Nc2MenuDetail->find('all', $query);

		$MenuFrameSetting->begin();

		try {
			$data['MenuFrameSetting'] = $this->generateNc3MenuFrameSettingData($nc2MenuDetails[0]);
			if (!$data['MenuFrameSetting']) {
				$MenuFrameSetting->rollback();
				return true;	// 処理を継続するためtrueを返す
			}

			foreach ($nc2MenuDetails as $nc2MenuDetail) {
				$data = $this->generateNc3MenuFramePageOrRoomData($nc2MenuDetail, $data);
			}

			// 表示タイプによって、データがない場合（メニュー設定後に追加したページ）の扱いが違う。
			// @see https://github.com/NetCommons3/Menus/blob/3.1.0/Controller/MenusController.php#L90-L99
			// @see https://github.com/NetCommons3/Menus/blob/3.1.0/View/Helper/MenuHelper.php#L197
			// @see https://github.com/NetCommons3/Menus/blob/3.1.0/View/Helper/MenuHelper.php#L218
			$data = $this->addOtherRoomAndPageData($data);

			$MenuFrameSetting->create();
			if (!$MenuFrameSetting->saveMenuFrameSetting($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
				// ここでrollback
				$MenuFrameSetting->rollback();

				$message = $this->getLogArgument($nc2MenuDetail) . "\n" .
					var_export($MenuFrameSetting->validationErrors, true);
				$this->writeMigrationLog($message);
				return true;	// 処理を継続するためtrueを返す
			}

			$idMap = [
				$nc2BlockId => $MenuFrameSetting->id,
			];
			$this->saveMap('MenuFrameSetting', $idMap);

			$MenuFrameSetting->commit();

		} catch (Exception $ex) {
			$MenuFrameSetting->rollback($ex);
			throw $ex;
		}

		return true;
	}
}