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
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Room
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

		// 参加ユーザーのデータ量が多い可能性を考慮し、
		// ルーム毎にトラザクション処理を行う
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
		/* @var $RolesRoomsUser RolesRoomsUser */
		$Room = ClassRegistry::init('Rooms.Room');
		$RolesRoomsUser = ClassRegistry::init('Rooms.RolesRoomsUser');

		// 対応するルームが既存の処理について、対応させるデータが名前くらいしかない気がする。。。名前でマージして良いのか微妙なので保留
		//$this->saveExistingMap($nc2Pages);

		$currentLanguage = Current::read('Language');
		$isOtherLaguage = false;
		foreach ($nc2Pages as $key => $nc2Page) {
			/*
			if (!$this->isMigrationRow($nc2User)) {
				continue;
			}*/

			// 次レコードのNc2Page.permalinkが同じ場合は言語別のデータとする
			if (!$isOtherLaguage) {
				// 別のルームとするため初期化
				$nc2PageLaguages = [];
			}
			$nc2PageLaguages[] = $nc2Page;
			$key++;
			if (isset($nc2Pages[$key]) &&
				$nc2Pages[$key]['Nc2Page']['permalink'] == $nc2Page['Nc2Page']['permalink']
			) {
				$isOtherLaguage = true;
				continue;
			}
			$isOtherLaguage = false;

			$Room->begin();
			try {
				$data = $this->__generateNc3Data($nc2PageLaguages);
				if (!$data) {
					continue;
				}

				// saveする前に現在の言語を切り替える処理が必要
				// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/Model/Room.php#L516
				$this->__setCurrentLanguageId($data['RoomsLanguage']);

				if (!($data = $Room->saveRoom($data))) {
					// 各プラグインのsave○○にてvalidation error発生時falseが返っていくるがrollbackしていないので、 ここでrollback
					$Room->rollback();

					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2Page) . "\n" .
						var_export($Room->validationErrors, true);
					$this->writeMigrationLog($message);

					continue;
				}

				/*$data = $this->__generateNc3RolesRoomsUser($data, $nc2PageLaguages);
				if (!$RolesRoomsUser->saveRolesRoomsUsersForRooms($data)) {
					// RolesRoomsUser::saveRolesRoomsUsersForRoomsではreturn falseなし
					continue;
				}*/

				$this->__saveMapEachLaguage($nc2PageLaguages, $Room->id);

				$Room->commit();

			} catch (Exception $ex) {
				Current::write('Language', $currentLanguage);

				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $User::saveUser()でthrowされるとこの処理に入ってこない
				$Room->rollback($ex);
				throw $ex;
			}
		}

		Current::write('Language', $currentLanguage);

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

		// 対応するルームが既存の場合（初回移行時にマッピングされる）、更新しない方が良いと思う。
		foreach ($nc2PageLaguages as $nc2PageLaguage) {
			$nc2RoomId = $nc2PageLaguage['Nc2Page']['room_id'];
			if ($this->getMap($nc2RoomId)) {
				return $data;
			}
		}

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
			'space_id' => $spaceId,
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

		// Space::createRoomでデータを作成する際、page_layout_permittedも初期値nullでsetされる。
		// しかしながら、ルームの登録画面からは、page_layout_permittedがPOSTされないっぽい。 データがあると、Validationに引っかかる。
		// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/View/Elements/Rooms/edit_form.ctp
		// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/Model/Room.php#L226-L231
		unset(
			$data['Room']['page_layout_permitted'],
			$Room->data['Room']['page_layout_permitted']
		);

		$data['RoomsLanguage'] = $this->__generateNc3RoomsLanguage($data, $nc2PageLaguages);
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
 * Generate Nc3RoomsLanguage data.
 *
 * @param array $nc3Room Nc3Room data.
 * @param array $nc2PageLaguages Nc2Page data.
 * @return array Nc3PluginsRoom data.
 */
	private function __generateNc3RoomsLanguage($nc3Room, $nc2PageLaguages) {
		$nc2Page = $this->__getPreferredNc2Page($nc2PageLaguages);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$existsKeys = [];
		foreach ($nc2PageLaguages as $nc2PageLaguage) {
			$nc3LaguageId = $this->convertLanguage($nc2PageLaguage['Nc2Page']['lang_dirname']);

			// 1行文字数が多くなるので参照渡しのループ
			foreach ($nc3Room['RoomsLanguage'] as $key => &$nc3RoomLaguage) {
				if ($nc3RoomLaguage['language_id'] != $nc3LaguageId) {
					continue;
				}

				$nc3RoomLaguage['name'] = $nc2PageLaguage['Nc2Page']['page_name'];
				$nc3RoomLaguage['created'] = $this->convertDate($nc2PageLaguage['Nc2Page']['insert_time']);
				$nc3RoomLaguage['created_user'] = $Nc2ToNc3User->getCreatedUser($nc2PageLaguage['Nc2Page']);
				$existsKeys[] = $key;

				break;
			}
			unset($nc3RoomLaguage);	// 参照渡し解除
		}

		// Nc2に存在しない言語分のデータ設定
		// 1行文字数が多くなるので参照渡しのループ
		foreach ($nc3Room['RoomsLanguage'] as $key => &$nc3RoomLaguage) {
			if (in_array($key, $existsKeys)) {
				continue;
			}

			$nc3RoomLaguage['name'] = $nc2Page['Nc2Page']['page_name'];
			$nc3RoomLaguage['created'] = $this->convertDate($nc2Page['Nc2Page']['insert_time']);
			$nc3RoomLaguage['created_user'] = $Nc2ToNc3User->getCreatedUser($nc2Page['Nc2Page']);
		}
		unset($nc3RoomLaguage);	// 参照渡し解除

		return $nc3Room['RoomsLanguage'];
	}

/**
 * Generate Nc3PluginsRoom data.
 *
 * @param array $nc2PageLaguages Nc2Page data.
 * @return array Nc3PluginsRoom data.
 */
	private function __generateNc3PluginsRoom($nc2PageLaguages) {
		/* @var $Nc2PagesModulesLink AppModel */
		$Nc2PagesModulesLink = $this->getNc2Model('pages_modules_link');
		$nc2RoomIds = Hash::extract($nc2PageLaguages, '{n}.Nc2Page.room_id');
		$nc2PageModuleLinks = $Nc2PagesModulesLink->findAllByRoomId(
			$nc2RoomIds,
			'DISTINCT module_id',
			null,
			-1
		);

		/* @var $Nc2ToNc3Plugin Nc2ToNc3Plugin */
		$Nc2ToNc3Plugin = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Plugin');
		$map = $Nc2ToNc3Plugin->getMap();
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

/**
 * Set current language id
 *
 * @param array $nc3RoomLanguages Nc3RoomsLanguage data.
 * @return array Nc3PluginsRoom data.
 */
	private function __setCurrentLanguageId($nc3RoomLanguages) {
		$minCreated = '';
		foreach ($nc3RoomLanguages as $nc3RoomLanguage) {
			if (!$minCreated ||
				$nc3RoomLanguage['created'] < $minCreated
			) {
				$languageId = $nc3RoomLanguage['language_id'];
				$minCreated = $nc3RoomLanguage['created'];
			}
		}

		if ($languageId == Current::read('Language.id')) {
			return;
		}

		/* @var $Language Language */
		$Language = ClassRegistry::init('M17n.Language');
		$language = $Language->findById($languageId, null, null, -1);
		Current::write('Language', $language['Language']);
	}

/**
 * Generate Nc3PluginsRoom data.
 *
 * @param array $nc3Room Nc3Room data.
 * @param array $nc2PageLaguages Nc2Page data.
 * @return array Nc3PluginsRoom data.
 */
	private function __generateNc3RolesRoomsUser($nc3Room, $nc2PageLaguages) {
		/* @var $Nc2PagesUsersLink AppModel */
		$Nc2PagesUsersLink = $this->getNc2Model('pages_users_link');
		foreach ($nc2PageLaguages as $nc2PageLaguage) {
			$nc2PagesUsersLink = $Nc2PagesUsersLink->findAllByRoomId(
				$nc2PageLaguage['Nc2Page']['room_id'],
				null,
				null,
				-1
			);
		}
	}

/**
 * Save map
 *
 * @param array $nc2PageLaguages Nc2Page data.
 * @param array $nc3RoomId Nc3Room id.
 * @return array Nc3PluginsRoom data.
 */
	private function __saveMapEachLaguage($nc2PageLaguages, $nc3RoomId) {
		foreach ($nc2PageLaguages as $nc2PageLaguage) {
			$nc2RoomId = $nc2PageLaguage['Nc2Page']['room_id'];
			if ($this->getMap($nc2RoomId)) {
				continue;
			}

			$idMap = [
				$nc2RoomId => $nc3RoomId
			];
			$this->saveMap('Room', $idMap);
		}
	}
}
