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
 * @method string getNc3DefaultRoleKeyByNc2SpaceType($nc2SpaceType)
 * @method array getNc3DefaultRolePermission()
 * @method string getNc2DefaultEntryRoleAuth($confName)
 * @method void changeNc3CurrentLanguage()
 * @method void restoreNc3CurrentLanguage()
 *
 * @see Nc2ToNc3RoomBehavior
 * @method string getLogArgument($nc2Page)
 * @method array getNc2RoomConditions()
 * @method array getNc2OtherLaguageRoomIdList($nc2Page)
 * @method bool isNc2PagesUsersLinkToBeMigrationed($userMap, $nc2UserId, $nc2Page, $nc3RoleRoomUserList)
 * @method array getNc2PagesUsersLinkByRoomId($nc3Room, $nc2Page)
 * @method array getNc3RolesRoomsUserListByRoomIdAndUserId($nc3Room, $userMap)
 * @method array getNc3RoleRoomListByRoomId($nc3Room)
 * @method string getNc3RoleRoomIdByNc2RoleAuthotityId($nc3RoleRoomList, $nc2RoleAuthotityId)
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
 * Nc2Page.space_type of public
 *
 * @var int
 */
	const NC2_SPACE_TYPE_PUBLIC = '1';

/**
 * Nc2Page.space_type of group
 *
 * @var int
 */
	const NC2_SPACE_TYPE_GROUP = '2';

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Room Migration start.'));

		// permalinkが同じデータを言語別のデータとして移行するが、
		// 言語ごとに移行しないと、parent_idが移行済みである保証ができない
		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$query = [
			'fields' => 'DISTINCT lang_dirname',
			'conditions' => $this->getNc2RoomConditions(),
			'recursive' => -1
		];
		$nc2Pages = $Nc2Page->find('all', $query);

		// Nc2Config.languageを優先する。
		// Nc3Room.activeがちょっと問題かも。（準備中を優先した方が良い？）
		foreach ($nc2Pages as $key => $nc2Page) {
			$nc2LangDirname = $nc2Page['Nc2Page']['lang_dirname'];

			// Communityの場合はNc2Page.lang_dirnameが空なのでスルー
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

		// is_originの値はsaveする前に現在の言語を切り替える処理が必要
		// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/Model/Room.php#L516
		$this->changeNc3CurrentLanguage();

		foreach ($nc2Pages as $nc2Page) {
			if (!$this->__saveRoomFromNc2($nc2Page['Nc2Page']['lang_dirname'])) {
				$this->restoreNc3CurrentLanguage();
				return false;
			}
		}

		$this->restoreNc3CurrentLanguage();

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Room Migration end.'));
		return true;
	}

/**
 * Save UserAttribue from Nc2.
 *
 * @param string $nc2LangDirName Nc2Page lang_dirname.
 * @return bool True on success.
 * @throws Exception
 */
	private function __saveRoomFromNc2($nc2LangDirName) {
		/* @var $Nc2Page AppModel */
		$Nc2Page = $this->getNc2Model('pages');
		$conditions = $this->getNc2RoomConditions();
		$conditions += [
			'Nc2Page.lang_dirname' => $nc2LangDirName
		];
		$query = [
			'conditions' => $conditions,
			'order' => [
				'Nc2Page.parent_id',
			],
			'recursive' => -1
		];
		$nc2Pages = $Nc2Page->find('all', $query);

		/* @var $Room Room */
		/* @var $RolesRoomsUser RolesRoomsUser */
		$Room = ClassRegistry::init('Rooms.Room');
		$RolesRoomsUser = ClassRegistry::init('Rooms.RolesRoomsUser');

		// 対応するルームが既存の処理について、対応させるデータが名前くらいしかない気がする。。。名前でマージして良いのか微妙なので保留
		//$this->saveExistingMap($nc2Pages);

		foreach ($nc2Pages as $nc2Page) {
			/*
			if (!$this->isMigrationRow($nc2User)) {
				continue;
			}*/

			// $Room->saveRoomと$RolesRoomsUser->saveRolesRoomsUsersForRoomsのトランザクションを優先する
			// $Roomと$RolesRoomsUserを別々にcommitする
			//$Room->begin();
			try {
				$data = $this->__generateNc3Data($nc2Page);
				if (!$data) {
					continue;
				}

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

				// データ量が多い可能性あり、limitで分割登録した方が良いかも
				$data = $this->__generateNc3RolesRoomsUser($data, $nc2Page);
				if (!$RolesRoomsUser->saveRolesRoomsUsersForRooms($data)) {
					// RolesRoomsUser::saveRolesRoomsUsersForRoomsではreturn falseなし
					continue;
				}

				$nc2RoomId = $nc2Page['Nc2Page']['room_id'];
				if ($this->getMap($nc2RoomId)) {
					continue;
				}

				$idMap = [
					$nc2RoomId => $Room->id
				];
				$this->saveMap('Room', $idMap);

				//$Room->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $User::saveUser()でthrowされるとこの処理に入ってこない
				//$Room->rollback($ex);
				throw $ex;
			}
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
 * @param array $nc2Page Nc2Page data.
 * @return array Nc3Room data.
 */
	private function __generateNc3Data($nc2Page) {
		$data = [];

		// 対応するルームが既存の場合（初回移行時にマッピングされる）、更新しない方が良いと思う。
		if ($this->getMap($nc2Page['Nc2Page']['room_id'])) {
			return $data;
		}

		// 言語別のmapデータが存在する場合は、Room.name,Room.created,Room.created_userを移行
		$otherLaguageRoomIds = $this->getNc2OtherLaguageRoomIdList($nc2Page);
		$otherLaguageMap = $this->getMap($otherLaguageRoomIds);
		if ($otherLaguageMap) {
			return $this->__generateNc3ExistsRooms($nc2Page, $otherLaguageMap);
		}

		return $this->__generateNc3NotExistsRooms($nc2Page);
	}

/**
 * Generate Nc3RoomsLanguage data.
 *
 * @param array $nc2Page Nc2Page data.
 * @param array $otherLaguageMap Other laguage map data.
 * @return array Nc3Room data.
 */
	private function __generateNc3ExistsRooms($nc2Page, $otherLaguageMap) {
		/* @var $Room Room */
		$Room = ClassRegistry::init('Rooms.Room');

		// 別言語Mapデータは複数あっても、対応するNc3Room.idは1つ
		$nc3Room = current($otherLaguageMap);
		$data = $Room->findById($nc3Room['Room']['id']);
		$nc3LaguageId = $this->convertLanguage($nc2Page['Nc2Page']['lang_dirname']);
		foreach ($data['RoomsLanguage'] as $key => $nc3RoomLaguage) {
			if ($nc3RoomLaguage['language_id'] != $nc3LaguageId) {
				continue;
			}

			$data['RoomsLanguage'][$key] = $this->__generateNc3RoomsLanguage($nc3RoomLaguage, $nc2Page);
		}

		// Space::createRoomでデータを作成する際、page_layout_permittedも初期値nullでsetされる。
		// しかしながら、ルームの登録画面からは、page_layout_permittedがPOSTされないっぽい。 データがあると、Validationに引っかかる。
		// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/View/Elements/Rooms/edit_form.ctp
		// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/Model/Room.php#L226-L231
		unset($data['Room']['page_layout_permitted']);

		$data['PluginsRoom'] = $this->__generateNc3PluginsRoom($nc2Page);

		return $data;
	}

/**
 * Generate Nc3RoomsLanguage data.
 *
 * @param array $nc2Page Nc2Page data.
 * @return array Nc3Room data.
 */
	private function __generateNc3NotExistsRooms($nc2Page) {
		/* @var $Room Room */
		$Room = ClassRegistry::init('Rooms.Room');
		$spaces = $Room->getSpaces();
		$nc2SpaceType = $nc2Page['Nc2Page']['space_type'];

		/* @var $Space Space */
		if ($nc2SpaceType == self::NC2_SPACE_TYPE_PUBLIC) {
			$Space = ClassRegistry::init('PublicSpace.PublicSpace');
			$spaceId = Space::PUBLIC_SPACE_ID;
			$needApproval = '1';

		}
		if ($nc2SpaceType == self::NC2_SPACE_TYPE_GROUP) {
			$Space = ClassRegistry::init('CommunitySpace.CommunitySpace');
			$spaceId = Space::COMMUNITY_SPACE_ID;
			$needApproval = '0';
		}

		$parenId = $spaces[$spaceId]['Space']['room_id_root'];
		$map = $this->getMap($nc2Page['Nc2Page']['parent_id']);
		if ($map) {
			$parenId = $map['Room']['id'];
		}

		$defaultRoleKey = $spaces[$spaceId]['Room']['default_role_key'];
		if ($nc2Page['Nc2Page']['default_entry_flag'] == '1') {
			$defaultRoleKey = $this->getNc3DefaultRoleKeyByNc2SpaceType($nc2SpaceType);
		}

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [
			'space_id' => $spaceId,
			'root_id' => $spaces[$spaceId]['Space']['room_id_root'],	// 使ってないっぽい
			'parent_id' => $parenId,
			'active' => $nc2Page['Nc2Page']['display_flag'],
			'default_role_key' => $defaultRoleKey,
			'need_approval' => $needApproval,
			'default_participation' => $nc2Page['Nc2Page']['default_entry_flag'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Page['Nc2Page']),
			'created' => $this->convertDate($nc2Page['Nc2Page']['insert_time']),
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

		foreach ($data['RoomsLanguage'] as $key => $nc3RoomLaguage) {
			$data['RoomsLanguage'][$key] = $this->__generateNc3RoomsLanguage($nc3RoomLaguage, $nc2Page);
		}
		$data['RoomRolePermission'] = $this->getNc3DefaultRolePermission();
		$data['PluginsRoom'] = $this->__generateNc3PluginsRoom($nc2Page);

		return $data;
	}

/**
 * Generate Nc3RoomsLanguage data.
 *
 * @param array $nc3RoomLanguage Nc3RoomsLanguage data.
 * @param array $nc2Page Nc2Page data.
 * @return array Nc3RoomsLanguage data.
 */
	private function __generateNc3RoomsLanguage($nc3RoomLanguage, $nc2Page) {
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc3RoomLanguage['name'] = $nc2Page['Nc2Page']['page_name'];
		$nc3RoomLanguage['created_user'] = $Nc2ToNc3User->getCreatedUser($nc2Page['Nc2Page']);
		$nc3RoomLanguage['created'] = $this->convertDate($nc2Page['Nc2Page']['insert_time']);

		return $nc3RoomLanguage;
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
			null,
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
 * Generate Nc3RolesRoomsUser data.
 *
 * Data sample
 * data[RolesRoomsUser][0][id]:
 * data[RolesRoomsUser][0][room_id]:88
 * data[RolesRoomsUser][0][user_id]:99
 * data[RolesRoomsUser][0][roles_room_id]:77
 * data[RolesRoomsUser][1][id]:
 * data[RolesRoomsUser][1][room_id]:88
 * data[RolesRoomsUser][1][user_id]:999
 * data[RolesRoomsUser][1][roles_room_id]:777
 *
 * @param array $nc3Room Nc3Room data.
 * @param array $nc2Page Nc2Page data.
 * @return array Nc3PluginsRoom data.
 */
	private function __generateNc3RolesRoomsUser($nc3Room, $nc2Page) {
		$nc2PagesUsers = $this->getNc2PagesUsersLinkByRoomId($nc3Room, $nc2Page);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc2UserIds = Hash::extract($nc2PagesUsers, '{n}.Nc2PagesUsersLink.user_id');
		$userMap = $Nc2ToNc3User->getMap($nc2UserIds);

		$nc3RoleRoomUserList = $this->getNc3RolesRoomsUserListByRoomIdAndUserId($nc3Room, $userMap);
		$nc3RoleRoomList = $this->getNc3RoleRoomListByRoomId($nc3Room);

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$data = [];
		foreach ($nc2PagesUsers as $nc2PagesUser) {
			$nc2UserId = $nc2PagesUser['Nc2PagesUsersLink']['user_id'];
			$nc2RoleAuthotityId = $nc2PagesUser['Nc2PagesUsersLink']['role_authority_id'];

			$isMigrationRow = $this->isNc2PagesUsersLinkToBeMigrationed(
				$userMap,
				$nc2UserId,
				$nc2Page,
				$nc3RoleRoomUserList
			);
			if (!$isMigrationRow) {
				continue;
			}

			$nc3UserId = $userMap[$nc2UserId]['User']['id'];
			$nc3RolesRoomsUserId = Hash::get($nc3RoleRoomUserList, [$nc3UserId]);

			// 不参加のデータ
			if (!$nc2RoleAuthotityId &&
				$nc3RolesRoomsUserId
			) {
				$nc3RoleRoomUser = [
					'id' => $nc3RolesRoomsUserId,
					'delete' => true
				];
				$data[] = $nc3RoleRoomUser;
				continue;
			}

			$nc3RoleRoomUser = [
				'id' => $nc3RolesRoomsUserId,
				'room_id' => $nc3Room['Room']['id'],
				'user_id' => $nc3UserId,
				'roles_room_id' => $this->getNc3RoleRoomIdByNc2RoleAuthotityId($nc3RoleRoomList, $nc2RoleAuthotityId),
				// TODOーNC2MonthlyNumberから取得
				/*
				'access_count' => 0,
				'last_accessed' => null,
				'previous_accessed' => null,
				*/
				'created_user' => $Nc2ToNc3User->getCreatedUser($nc2PagesUser['Nc2PagesUsersLink']),
				'created' => $this->convertDate($nc2Page['Nc2Page']['insert_time']),
			];
			$data[] = $nc3RoleRoomUser;
		}

		$data['RolesRoomsUser'] = $data;

		return $data;
	}

}
