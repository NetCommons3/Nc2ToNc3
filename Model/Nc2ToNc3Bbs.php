<?php
/**
 * Nc2ToNc3Bbs
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Bbs
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
 */
class Nc2ToNc3Bbs extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Bbs'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Bbs Migration start.'));

		/* @var $Nc2Bbs AppModel */

		/* @var $Nc2BbsBlock AppModel */
		$Nc2Bbs = $this->getNc2Model('bbs');
		$nc2Bbses = $Nc2Bbs->find('all');

		if (!$this->__saveNc3BbsFromNc2($nc2Bbses)) {
			return false;
		}

		//親子関係を維持するため、Post ID順に取得
		$query = [
			'order' => [
				'post_id', 'parent_id'
			],
		];

		$Nc2BbsPost = $this->getNc2Model('bbs_post');
		$nc2BbsPosts = $Nc2BbsPost->find('all', $query);

		if (!$this->__saveNc3BbsArticleFromNc2($nc2BbsPosts)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Bbs Migration end.'));
		return true;
	}

/**
 * Save BbsFrameSetting from Nc2.
 *
 * @param array $nc2Bbses Nc2Bbs data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3BbsFromNc2($nc2Bbses) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Bbs data Migration start.'));

		/* @var $BbsFrameSetting BbsFrameSetting */
		$Bbs = ClassRegistry::init('Bbses.Bbs');

		Current::write('Plugin.key', 'Bbses');
		//Announcement モデルでBlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		$Bbs->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Bbs->Behaviors->Block->settings = $Bbs->actsAs['Blocks.Block'];

		$Nc2BbsBlock = $this->getNc2Model('bbs_block');

		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');

		foreach ($nc2Bbses as $nc2Bbs) {
			//var_dump($nc2Bbs);exit;
			/** @var array $nc2BbsBlock */
			$nc2BbsBlock = $Nc2BbsBlock->findByBbsId($nc2Bbs['Nc2Bb']['bbs_id'], null, null, -1);
			if (!$Nc2BbsBlock) {
				continue;
			}
			$Bbs->begin();
			try {
				$data = $this->generateNc3BbsData($nc2Bbs, $nc2BbsBlock);
				if (!$data) {
					$Bbs->rollback();
					continue;
				}

				$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				$nc3Room = $Nc2ToNc3Room->getMap($nc2Bbs['Nc2Bb']['room_id']);
				$nc3RoomId = $nc3Room['Room']['id'];
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$Bbs->create();
				$Block->create();
				$Topic->create();

				if (!$Bbs->saveBbs($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2Bbs) . "\n" .
						var_export($Bbs->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2BbsId = $nc2Bbs['Nc2Bb']['bbs_id'];
				$idMap = [
					$nc2BbsId => $Bbs->id
				];
				$this->saveMap('Bbs', $idMap);
				$Bbs->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BbsFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$Bbs->rollback($ex);
				throw $ex;
			}
		}
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Bbs data Migration end.'));
		return true;
	}

/**
 * Save BbsPost from Nc2.
 *
 * @param array $nc2BbsPosts Nc2BbsPost data.
 * @return bool True on success
 * @throws Exception
 */

	private function __saveNc3BbsArticleFromNc2($nc2BbsPosts) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Bbs Article data Migration start.'));

		/* @var $BbsFrameSetting BbsFrameSetting */
		$BbsArticle = ClassRegistry::init('Bbses.BbsArticle');

		Current::write('Plugin.key', 'bbses');
		//Announcement モデルで	BlockBehavior::settings[nameHtml]:true になるため、ここで明示的に設定しなおす
		//$BbsArticle->Behaviors->Block->settings['nameHtml'] = false;

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		//$BbsArticle->Behaviors->Block->settings = $BbsArticle->actsAs['Blocks.Block'];

		$Nc2BbsPostBody = $this->getNc2Model('bbs_post_body');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		$BbsArticleTree = ClassRegistry::init('Bbses.BbsArticleTree');

		foreach ($nc2BbsPosts as $nc2BbsPost) {

			$nc2BbsPostBody = $Nc2BbsPostBody->findByPostId($nc2BbsPost['Nc2BbsPost']['post_id'], null, null, -1);
			$BbsArticle->begin();
			try {
				$data = $this->generateNc3BbsArticleData($nc2BbsPost, $nc2BbsPostBody);
				if (!$data) {
					$BbsArticle->rollback();
					continue;
				}

				$Block = ClassRegistry::init('Blocks.Block');
				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				Current::write('Room.id', $nc3RoomId);

				$BlocksLanguage->create();
				$BbsArticle->create();

				//false抜きだと、余計なデフォルト値(lft、rght)をいれてしまう。
				$BbsArticleTree->create(false);
				$Block->create();
				$Topic->create();

				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// Hash::merge で BbsArticle::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Bbses/blob/3.1.0/Model/BbsArticle.php#L138-L141
				$BbsArticle->validate = [];
				$BbsArticleTree->validate = [];

				//error_log(print_r('dddddddddddddddddddddddddddddddddddd', true)."\n\n", 3, LOGS."/debug.log");

				if (!$BbsArticle->saveBbsArticle($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2BbsPost) . "\n" .
						var_export($BbsArticle->validationErrors, true);
					$this->writeMigrationLog($message);
					continue;
				}

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2PostId = $nc2BbsPost['Nc2BbsPost']['post_id'];
				$idMap = [
					$nc2PostId => $BbsArticle->id
				];
				$this->saveMap('BbsArticle', $idMap);
				$BbsArticle->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BbsFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$BbsArticle->rollback($ex);
				throw $ex;
			}
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Bbs Article data Migration end.'));
		return true;
	}

}