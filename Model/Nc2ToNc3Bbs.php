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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Bbs',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
		'Nc2ToNc3.Nc2ToNc3BlockRolePermission',
	];

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

		/* @var $Nc2BbsBlock AppModel */
		$Nc2BbsBlock = $this->getNc2Model('bbs_block');
		$nc2BbsBlocks = $Nc2BbsBlock->find('all');
		if (!$this->__saveNc3BbsFrameSettingFromNc2($nc2BbsBlocks)) {
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
		$BbsSetting = ClassRegistry::init('Bbses.BbsSetting');
		$MailSetting = ClassRegistry::init('Mails.MailSetting');

		/* @see Nc2ToNc3Map::getMapIdList() */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$mapRoomIdList = $Nc2ToNc3Map->getMapIdList('Room');

		foreach ($nc2Bbses as $nc2Bbs) {
			//var_dump($nc2Bbs);exit;
			/** @var array $nc2BbsBlock */
			$nc2BbsBlock = $Nc2BbsBlock->findByBbsId($nc2Bbs['Nc2Bb']['bbs_id'], null, null, -1);
			// nc2配置してなくても移行する
			//if (!$nc2BbsBlock) {
			//	continue;
			//}
			$Bbs->begin();
			try {
				$nc2RoomId = $nc2Bbs['Nc2Bb']['room_id'];
				// nc3 room_id取得
				if (! isset($mapRoomIdList[$nc2RoomId])) {
					// 基本ありえない想定
					$message = __d('nc2_to_nc3', '%s No room ID corresponding to nc3',
						'nc2_room_id:' . $nc2RoomId);
					$this->writeMigrationLog($message);
					$Bbs->rollback();
					continue;
				}
				$nc3RoomId = $mapRoomIdList[$nc2RoomId];

				$data = $this->generateNc3BbsData($nc2Bbs, $nc2BbsBlock, $nc3RoomId);
				if (!$data) {
					$Bbs->rollback();
					continue;
				}

				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L365
				Current::write('Block.id', $data['Block']['id']);

				$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
				$nc3Room = $Nc2ToNc3Room->getMap($nc2Bbs['Nc2Bb']['room_id']);
				$nc3RoomId = $nc3Room['Room']['id'];
				Current::write('Room.id', $nc3RoomId);
				Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$BlocksLanguage->create();
				$Bbs->create();
				$Block->create();
				$Topic->create();
				$BbsSetting->create();
				$MailSetting->create();

				if (!$Bbs->saveBbs($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$Bbs->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2Bbs) . "\n" .
						var_export($Bbs->validationErrors, true);
					$this->writeMigrationLog($message);
					$Bbs->rollback();
					continue;
				}

				$bbs = $Bbs->findById($Bbs->id, 'block_id', null, -1);
				$block = $Block->findById($bbs['Bbs']['block_id'], null, null, -1);
				Current::write('Block', $block['Block']);
				foreach ($data['BlockRolePermission'] as &$permission) {
					foreach ($permission as &$role) {
						$role['block_key'] = $block['Block']['key'];
					}
				}

				if (!$BbsSetting->saveBbsSetting($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$BbsSetting->rollback();

					$message = $this->getLogArgument($data) . "\n" .
						var_export($BbsSetting->validationErrors, true);
					$this->writeMigrationLog($message);
					$Bbs->rollback();

					continue;
				}

				// MailSetting
				$data['MailSetting']['block_key'] = $block['Block']['key'];
				$data['MailSettingFixedPhrase'][0]['block_key'] = $block['Block']['key'];

				// メールの権限については先に権限設定保存じに保存できちゃってるので、ここでは保存させない
				unset($data['BlockRolePermission']);
				if (!$MailSetting->saveMailSettingAndFixedPhrase($data)) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、ここでrollback
					$MailSetting->rollback();

					$message = $this->getLogArgument($data) . "\n" .
						var_export($MailSetting->validationErrors, true);
					$this->writeMigrationLog($message);
					$Bbs->rollback();
					continue;
				}

				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

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

			Current::remove('Block');
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
		$Like = ClassRegistry::init('Likes.Like');

		foreach ($nc2BbsPosts as $nc2BbsPost) {

			$nc2BbsPostBody = $Nc2BbsPostBody->findByPostId($nc2BbsPost['Nc2BbsPost']['post_id'], null, null, -1);
			$BbsArticle->begin();
			try {
				$data = $this->generateNc3BbsArticleData($nc2BbsPost, $nc2BbsPostBody);
				if (!$data) {
					$BbsArticle->rollback();
					continue;
				}

				$Blocks = $Block->findById($data['Block']['id'], null, null, -1);
				$nc3RoomId = $Blocks['Block']['room_id'];

				Current::write('Room.id', $nc3RoomId);

				$BlocksLanguage->create();
				$BbsArticle->create();

				//false抜きだと、余計なデフォルト値(lft、rght)をいれてしまう。
				$BbsArticleTree->create(false);
				$Block->create();
				$Topic->create();

				Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				// Hash::merge で BbsArticle::validate['publish_start']['datetime']['rule']が
				// ['datetime','datetime'] になってしまうので初期化
				// @see https://github.com/NetCommons3/Bbses/blob/3.1.0/Model/BbsArticle.php#L138-L141
				$BbsArticle->validate = [];
				$BbsArticleTree->validate = [];

				if (!($nc3BbsArticle = $BbsArticle->saveBbsArticle($data))) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
					// ここでrollback
					$BbsArticle->rollback();
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html

					$message = $this->getLogArgument($nc2BbsPost) . "\n" .
						var_export($BbsArticle->validationErrors, true);
					$this->writeMigrationLog($message);
					$BbsArticle->rollback();
					continue;
				}
				if (isset($data['Like'])) {
					$data['Like']['content_key'] = $nc3BbsArticle['BbsArticle']['key'];
					$Like->create();
					$Like->save($data);
				}

				unset(Current::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

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

/**
 * Save BbsFrameSetting from Nc2.
 *
 * @param array $nc2BbsBlocks Nc2BbsBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveNc3BbsFrameSettingFromNc2($nc2BbsBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  BbsFrameSetting data Migration start.'));

		/* @var $BbsFrameSetting BbsFrameSetting */
		/* @var $Frame Frame */
		$BbsFrameSetting = ClassRegistry::init('Bbses.BbsFrameSetting');
		$Frame = ClassRegistry::init('Frames.Frame');
		foreach ($nc2BbsBlocks as $nc2BbsBlock) {
			$BbsFrameSetting->begin();
			try {
				$data = $this->generateNc3BbsFrameSettingData($nc2BbsBlock);
				if (!$data) {
					$BbsFrameSetting->rollback();
					continue;
				}

				$BbsFrameSetting->create();
				if (!$BbsFrameSetting->saveBbsFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2BbsBlock) . "\n" .
						var_export($BbsFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$BbsFrameSetting->rollback();
					continue;
				}

				if (!$Frame->saveFrame($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2BbsBlock) . "\n" .
						var_export($BbsFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$BbsFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2BbsBlock['Nc2BbsBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $BbsFrameSetting->id
				];
				$this->saveMap('BbsFrameSetting', $idMap);

				$BbsFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $BbsFrameSetting::saveBbsFrameSetting()でthrowされるとこの処理に入ってこない
				$BbsFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		/*
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Block.id');
		*/

		$this->writeMigrationLog(__d('nc2_to_nc3', '  BbsFrameSetting data Migration end.'));

		return true;
	}

}
