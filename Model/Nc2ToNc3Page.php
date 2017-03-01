<?php
/**
 * Nc2ToNc3Page
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Page
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
 * @see Nc2ToNc3PageBaseBehavior
 * @method string convertPermalink($nc2Permalink)
 *
 * @see Nc2ToNc3PageBehavior
 * @method string getLogArgument($nc2Item)
 * @method void saveExistingMap($nc2Pages)
 * @method array getNc2PageConditions()
 * @method string getNc3RootId($nc2Page, $roomMap)
 *
 */
class Nc2ToNc3Page extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Page'];

/**
 * Number of validation error
 *
 * @var int
 */
	private $__numberOfValidationError = 0;

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Page Migration start.'));

		// permalinkが同じデータを言語別のデータとして移行するが、
		// 言語ごとに移行しないと、parent_idが移行済みである保証ができない
		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$query = [
			'fields' => 'DISTINCT lang_dirname',
			'conditions' => $this->getNc2PageConditions(),
			'recursive' => -1
		];
		$nc2Pages = $Nc2Page->find('all', $query);

		// Nc2Config.languageを優先する。
		// Nc3PageLaguage.is_originはNc2Config.languageを優先してtrueにする
		// @see https://github.com/NetCommons3/M17n/blob/3.1.0/Model/Behavior/M17nBehavior.php#L220-L233
		foreach ($nc2Pages as $key => $nc2Page) {
			$nc2LangDirname = $nc2Page['Nc2Page']['lang_dirname'];

			// Community,Privateの場合はNc2Page.lang_dirnameが空なのでスルー
			if (!$nc2LangDirname) {
				continue;
			}

			$nc3LaguageId = $this->convertLanguage($nc2LangDirname);
			if (!$nc3LaguageId) {
				unset($nc2Pages[$key]);
				continue;
			}

			if ($nc3LaguageId == $this->getLanguageIdFromNc2()) {
				unset($nc2Pages[$key]);
				array_unshift($nc2Pages, $nc2Page);
			}
		}

		foreach ($nc2Pages as $nc2Page) {
			if (!$this->__savePageFromNc2WhileDividing($nc2Page['Nc2Page']['lang_dirname'])) {
				return false;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Page Migration end.'));

		return true;
	}

/**
 * Save Page from Nc2 while dividing.
 *
 * @param string $nc2LangDirName Nc2Page lang_dirname.
 * @return bool True on success.
 */
	private function __savePageFromNc2WhileDividing($nc2LangDirName) {
		$limit = 1000;

		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$conditions = $this->getNc2PageConditions();
		$conditions += [
			'Nc2Page.lang_dirname' => $nc2LangDirName
		];
		$query = [
			'conditions' => $conditions,
			'order' => [
				'Nc2Page.parent_id',
			],
			'limit' => $limit,
			'offset' => 0,
		];

		$numberOfUsers = 0;
		while ($nc2Pages = $Nc2Page->find('all', $query)) {
			if (!$this->__savePageFromNc2($nc2Pages)) {
				return false;
			}

			$numberOfUsers += count($nc2Pages);
			$errorRate = round($this->__numberOfValidationError / $numberOfUsers);
			// 5割エラー発生で止める
			if ($errorRate >= 0.5) {
				$this->validationErrors = [
					'database' => [
						__d('nc2_to_nc3', 'Many error data.Please check the log.')
					]
				];
				return false;
			}

			$query['offset'] += $limit;
		}

		return true;
	}

/**
 * Save Page from Nc2.
 *
 * @param array $nc2Pages Nc2Page data.
 * @return bool True on success
 * @throws Exception
 */
	private function __savePageFromNc2($nc2Pages) {
		/* @var $Page Page */
		$Page = ClassRegistry::init('Pages.Page');

		$this->saveExistingMap($nc2Pages);
		foreach ($nc2Pages as $nc2Page) {
			$Page->begin();
			try {
				/*
				if (!$this->isMigrationRow($nc2User)) {
					$Page->rollback();
					continue;
				}*/

				$data = $this->__generateNc3Data($nc2Page);
				if (!$data) {
					$Page->rollback();
					continue;
				}

				$Page->create(false);
				if (!$Page->savePage($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Page->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Page) . "\n" .
						var_export($Page->validationErrors, true);
					$this->writeMigrationLog($message);

					$this->__numberOfValidationError++;

					$Page->rollback();
					continue;
				}

				$nc2PageId = $nc2Pages['Nc2Page']['page_id'];
				if ($this->getMap($nc2PageId)) {
					$Page->commit();
					continue;
				}

				$idMap = [
					$nc2PageId => $Page->id
				];
				$this->saveMap('Page', $idMap);

				$Page->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $Page::savePage()でthrowされるとこの処理に入ってこない
				$Page->rollback($ex);
				throw $ex;
			}
		}

		return true;
	}

/**
 * Generate nc3 data
 *
 * Data sample
 * data[Page][id]:
 * data[Page][root_id]:1
 * data[Page][parent_id]:1
 * data[Page][permalink]:page_20170227014607
 * data[Page][room_id]:1
 * data[Room][id]:1
 * data[Room][space_id]:2
 * data[PagesLanguage][id]:
 * data[PagesLanguage][language_id]:2
 * data[PagesLanguage][name]:ページ名
 *
 * @param array $nc2Page Nc2Page data.
 * @return array Nc3Room data.
 */
	private function __generateNc3Data($nc2Page) {
		$data = [];

		// 対応するページが既存の場合（初回移行時にマッピングされる）上書き
		$pageMap = $this->getMap($nc2Page['Nc2Page']['page_id']);
		if ($pageMap) {
			/* @var $PagesLanguage PagesLanguage */
			$PagesLanguage = ClassRegistry::init('Pages.PagesLanguage');
			$data = $PagesLanguage->getPagesLanguage(
				$pageMap['Page']['id'],
				$this->convertLanguage($nc2Page['Nc2Page']['lang_dirname'])
			);
		}

		return $this->__generateNc3Page($nc2Page, $data);
	}

/**
 * Generate Nc3Page data.
 *
 * @param array $nc2Page Nc2Page data.
 * @param array $nc3Page Nc3Page data.
 * @return array Nc3Page data.
 */
	private function __generateNc3Page($nc2Page, $nc3Page) {
		$data = [];

		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$roomMap = $Nc2ToNc3Room->getMap($nc2Page['Nc2Page']['room_id']);
		$map = $this->getMap($nc2Page['Nc2Page']['parent_id']);
		$data = [
			'Page' => [
				'room_id' => $roomMap['Room']['id'],
				'root_id' => $this->getNc3RootId($nc2Page, $roomMap),
				'parent_id' => $map['Page']['id'],
			]
		];
		if ($nc3Page) {
			$data = array_merge($nc3Page, $data);
		}

		// 先頭のNc2Page.permalinkは空だが、Validationにひっかかるための処理
		// Page.slugに設定すれば良い？
		// @see https://github.com/NetCommons3/Pages/blob/3.0.1/Controller/PagesEditController.php#L151
		// @see https://github.com/NetCommons3/Pages/blob/3.0.1/Model/Behavior/PageSaveBehavior.php#L49-L68
		$nc3Slug = $this->convertPermalink($nc2Page['Nc2Page']['permalink']);
		if ($nc3Slug) {
			$data['Page']['slug'] = $nc3Slug;
		}

		$nc3LaguageId = $this->convertLanguage($nc2Page['Nc2Page']['lang_dirname']);
		if (!$nc3LaguageId) {
			$nc3LaguageId = $this->getLanguageIdFromNc2();
		}

		$data['PagesLanguage'] = [
			'language_id' => $nc3LaguageId,
			'name' => $nc2Page['Nc2Page']['page_name'],
		];

		return $data;
	}

}
