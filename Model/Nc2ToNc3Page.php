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
 *
 * @see Nc2ToNc3PageBehavior
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
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Page Migration start.'));

		if (!$this->__savePageFromNc2WhileDividing()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Page Migration end.'));
		return true;
	}

/**
 * Save Page from Nc2 while dividing.
 *
 * @return bool True on success.
 */
	private function __savePageFromNc2WhileDividing() {
		$limit = 1000;

		/* @var $Nc2User AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$query = [
			'conditions' => [
				'Nc2Page.page_id != Nc2Page.room_id',
				'Nc2Page.parent_id !=' => '0',
			],
			'order' => [
				'Nc2Page.parent_id',
			],
			'limit' => $limit,
			'offset' => 0,
		];

		while ($nc2Pages = $Nc2Page->find('all', $query)) {
			if (!$this->__savePageFromNc2($nc2Pages)) {
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
		/* @var $User User */
		$Page = ClassRegistry::init('Pages.Page');

		$Page->begin();
		try {
			//$this->saveExistingMap($nc2Users);
			foreach ($nc2Pages as $nc2Page) {
				$data = $nc2Page;
				//var_dump($nc2Page);
				/*
				if (!$this->isMigrationRow($nc2User)) {
					continue;
				}

				$data = $this->__generateNc3Data($nc2User);
				if (!$data) {
					continue;
				}

				if (!$User->saveUser($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返っていくるがrollbackしていないので、
					// ここでrollback
					$User->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2User) . "\n" .
						var_export($User->validationErrors, true);
					$this->writeMigrationLog($message);

					$this->__numberOfvalidationError++;

					continue;
				}

				// User::beforeValidateでValidationを設定しているが、残ってしまうので1行ごとにクリア
				$User->validate = [];

				$nc2UserId = $nc2User['Nc2User']['user_id'];
				if ($this->getMap($nc2UserId)) {
					continue;
				}

				$idMap = [
					$nc2UserId => $User->id
				];
				$this->saveMap('User', $idMap);
				*/
			}

			unset($data);
			$Page->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $Page::savePage()でthrowされるとこの処理に入ってこない
			$Page->rollback($ex);
			throw $ex;
		}

		return true;
	}

}
