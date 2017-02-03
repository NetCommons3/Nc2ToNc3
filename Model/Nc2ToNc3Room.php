<?php
/**
 * Nc2ToNc3Room
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Room
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 *
 * @see Nc2ToNc3RoomBaseBehavior
 * @method string getDefaultRoleKeyFromNc2($nc2SpaceType)
 * @method array getNc3DefaultRolePermission()
 *
 * @see Nc2ToNc3RoomsBehavior
 *
 */
class Nc2ToNc3Room extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Room'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Room Migration start.'));

		if (!$this->__saveRoomFromNc2()) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Room Migration end.'));
		return true;
	}

/**
 * Save UserAttribue from Nc2.
 *
 * @return bool True on success
 * @throws Exception
 */
	private function __saveRoomFromNc2() {
		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$query = [
			'conditions' => [
				'Nc2Page.page_id = Nc2Page.room_id',
				'Nc2Page.private_flag' => '0',
				'Nc2Page.root_id !=' => '0',
			],
			'order' => [
				'Nc2Page.permalink',
				'Nc2Page.lang_dirname',
			],
		];
		$nc2Pages = $Nc2Page->find('all', $query);

		/* @var $Room Room */
		$Room = ClassRegistry::init('Rooms.Room');
		$Room->begin();
		try {
			// 対応するルームが既存の処理
			// 対応させるデータが名前くらいしかない気がする。。。
			// 名前でマージして良いのか微妙なので保留
			//$this->putExistingIdMap($nc2Pages);
			foreach ($nc2Pages as $key => $nc2Page) {
				/*
				if (!$this->isMigrationRow($nc2User)) {
					continue;
				}
				*/

				// 次レコードのNc2Page.permalinkが同じ場合は言語別のデータとする
				$nc2PageLaguages[] = $nc2Page;
				$key++;
				if (isset($nc2Pages[$key]) &&
					$nc2Pages[$key]['Nc2Page']['permalink'] == $nc2Page['Nc2Page']['permalink']
				) {
					continue;
				}

				$data = $this->__generateNc3Data($nc2PageLaguages);
				if (!$data) {
					continue;
				}
				var_dump($data);
				$nc2PageLaguages = [];
				continue;

				// saveする前に現在の言語を切り替える処理が必要

				/*
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
				if ($this->getIdMap($nc2UserId)) {
					continue;
				}

				$this->putIdMap($nc2UserId, $User->data);
				*/
			}

			$Room->commit();

		} catch (Exception $ex) {
			// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
			// $User::saveUser()でthrowされるとこの処理に入ってこない
			$Room->rollback($ex);
			throw $ex;
		}

		return true;
	}

/**
 * Generate nc3 data
 *
 * Data sample
 * data[Room][id]:
 * data[Room][space_id]:4
 * data[Room][root_id]:43
 * data[Room][parent_id]:3
 * data[Page][parent_id]:
 * data[RoomsLanguage][0][id]:
 * data[RoomsLanguage][0][room_id]:
 * data[RoomsLanguage][0][language_id]:2
 * data[RoomsLanguage][0][name]:sample
 * data[Room][default_participation]:0
 * data[Room][default_role_key]:general_user
 * data[Room][need_approval]:0
 * data[RoomRolePermission][content_publishable][chief_editor][value]:0
 * data[RoomRolePermission][content_publishable][chief_editor][value]:1
 * data[RoomRolePermission][content_publishable][editor][value]:0
 * data[RoomRolePermission][content_publishable][room_administrator][id]:
 * data[RoomRolePermission][content_publishable][chief_editor][id]:
 * data[RoomRolePermission][content_publishable][editor][id]:
 * data[RoomRolePermission][html_not_limited][room_administrator][value]:0
 * data[RoomRolePermission][html_not_limited][chief_editor][value]:0
 * data[RoomRolePermission][html_not_limited][editor][value]:0
 * data[RoomRolePermission][html_not_limited][room_administrator][id]:
 * data[RoomRolePermission][html_not_limited][chief_editor][id]:
 * data[RoomRolePermission][html_not_limited][editor][id]:
 * data[Room][active]:1
 *
 * @param array $nc2PageLaguages Nc2Page data.
 * @return array Nc3Room data.
 */
	private function __generateNc3Data($nc2PageLaguages) {
		$data = [];
		$nc2Page = $this->__getPreferredNc2Page($nc2PageLaguages);

		/*
		// 対応するルームが既存の処理
		// 対応させるデータが名前くらいしかない気がする。。。
		// 名前でマージして良いのか微妙なので保留
		$nc2RoomId = $nc2Page['Nc2Page']['room_id'];
		$idMap = $this->getIdMap($nc2RoomId);
		if ($idMap) {
			$data = 既存ルーム取得;
		} else {
			$data = 新規作成;
		}
		*/

		/* @var $Room Room */
		$Room = ClassRegistry::init('Rooms.Room');
		$spaces = $Room->getSpaces();
		$nc2SpaceType = $nc2Page['Nc2Page']['space_type'];

		if ($nc2SpaceType == '1') {
			/* @var $Space PublicSpace */
			$Space = ClassRegistry::init('PublicSpace.PublicSpace');
			$spaceId = Space::PUBLIC_SPACE_ID;
			$needApproval = '1';

		}
		if ($nc2SpaceType == '2') {
			/* @var $Space CommunitySpace */
			$Space = ClassRegistry::init('CommunitySpace.CommunitySpace');
			$spaceId = Space::COMMUNITY_SPACE_ID;
			$needApproval = '0';
		}

		$defaultRoleKey = $spaces[$spaceId]['Room']['default_role_key'];
		if ($nc2Page['Nc2Page']['default_entry_flag'] == '1') {
			$defaultRoleKey = $this->getDefaultRoleKeyFromNc2($nc2SpaceType);
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'space_id' => Space::PUBLIC_SPACE_ID,
			'root_id' => $spaces[$spaceId]['Space']['room_id_root'],	// 使ってないっぽい
			'parent_id' => $spaces[$spaceId]['Space']['room_id_root'],
			'active' => $nc2Page['Nc2Page']['display_flag'],
			'default_role_key' => $defaultRoleKey,
			'need_approval' => $needApproval,
			'default_participation' => $nc2Page['Nc2Page']['default_entry_flag'],
			'created' => $this->convertDate($nc2Page['Nc2Page']['insert_time']),
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Page['Nc2Page'])
		];
		$data = $Space->createRoom($data);

		$existsKeys = [];
		foreach ($nc2PageLaguages as $nc2PageLaguage) {
			$nc3LaguageId = $this->convertLanguage($nc2PageLaguage['Nc2Page']['lang_dirname']);

			foreach ($data['RoomsLanguage'] as $key => $nc3RoomLaguage) {
				if ($nc3RoomLaguage['language_id'] == $nc3LaguageId) {
					$data['RoomsLanguage'][$key]['name'] = $nc2PageLaguage['Nc2Page']['page_name'];
					$existsKeys[] = $key;
					continue 2;
				}
			}
		}

		// Nc2に存在しない言語分のデータ設定
		foreach ($data['RoomsLanguage'] as $key => $nc3RoomLaguage) {
			if (in_array($key, $existsKeys)) {
				continue;
			}

			$data['RoomsLanguage'][$key]['name'] = $nc2Page['Nc2Page']['page_name'];
		}

		$data['RoomRolePermission'] = $this->getNc3DefaultRolePermission();
		$data['PluginsRoom'] = $this->__generateNc3PluginsRoom($nc2Page);

		return $data;
	}

/**
 * Get preferred Nc2Page data.
 *
 * @param array $nc2PageLaguages Nc2Page data.
 * @return array Nc2Page data.
 */
	private function __getPreferredNc2Page($nc2PageLaguages) {
		$nc2Page = [];

		// Nc2Config.languageを優先する。
		// Nc2Config.languageのルームがなければ最初のNc2Pageデータを使用する。
		// Nc3Room.activeがちょっと問題かも。（準備中を優先した方が良い？）
		foreach ($nc2PageLaguages as $nc2PageLaguage) {
			$nc3LaguageId = $this->convertLanguage($nc2PageLaguage['Nc2Page']['lang_dirname']);
			if (!$nc2Page ||
				$nc3LaguageId == $this->getLanguageIdFromNc2()
			) {
				$nc2Page = $nc2PageLaguage;
			}
		}

		return $nc2Page;
	}

/**
 * Generate Nc3PluginsRoom data.
 *
 * @param array $nc2Page Nc2Page data.
 * @return array Nc3PluginsRoom data.
 */
	private function __generateNc3PluginsRoom($nc2Page) {
		/* @var $Nc2PagesModulesLink AppModel */
		$Nc2PagesModulesLink = $this->getNc2Model('pages_modules_link');
		$nc2PageModuleLinks = $Nc2PagesModulesLink->findAllByRoomId(
			$nc2Page['Nc2Page']['room_id'],
			'module_id',
			null,
			-1
		);

		/* @var $Nc2ToNc3Plugin Nc2ToNc3Plugin */
		$Nc2ToNc3Plugin = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Plugin');
		$map = $Nc2ToNc3Plugin->getIdMap();
		$notExistsKeys = [
			'auth'
		];
		$nc3PluginsRoom['plugin_key'] = [];
		foreach ($nc2PageModuleLinks as $nc2PageModuleLink) {
			$nc2ModuleId = $nc2PageModuleLink['Nc2PagesModulesLink']['module_id'];
			if (!isset($map[$nc2ModuleId]['Plugin']['key']) ||
				in_array($map[$nc2ModuleId]['Plugin']['key'], $notExistsKeys)
			) {
				continue;
			}

			$nc3PluginsRoom['plugin_key'][] = $map[$nc2ModuleId]['Plugin']['key'];
		}

		return $nc3PluginsRoom;
	}

}
