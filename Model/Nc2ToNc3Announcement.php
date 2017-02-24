<?php
/**
 * Nc2ToNc3Announcement
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Fujiki Hideyuki <TriangleShooter@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('Current', 'NetCommons.Utility');

/**
 * Nc2ToNc3Announcement
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
 * @see Nc2ToNc3AnnouncementBaseBehavior
 * @method string getNc3DefaultRoleKeyByNc2SpaceType($nc2SpaceType)
 * @method array getNc3DefaultRolePermission()
 * @method string getNc2DefaultEntryRoleAuth($confName)
 * @method void changeNc3CurrentLanguage()
 * @method void restoreNc3CurrentLanguage()
 *
 * @see Nc2ToNc3AnnouncementBehavior
 * @method string getLogArgument($nc2Page)
 * @method array getNc2RoomConditions()
 * @method array getNc2OtherLaguageRoomIdList($nc2Page)
 * @method bool isNc2PagesUsersLinkToBeMigrationed($userMap, $nc2UserId, $nc2Page, $nc3RoleRoomUserList)
 * @method array getNc2PagesUsersLinkByRoomId($nc3Room, $nc2Page)
 * @method array getNc3RolesRoomsUserListByRoomIdAndUserId($nc3Room, $userMap)
 * @method array getNc3RoleRoomListByRoomId($nc3Room)
 * @method string getNc3RoleRoomIdByNc2RoleAuthotityId($nc3RoleRoomList, $nc2RoleAuthotityId)
 */
class Nc2ToNc3Announcement extends Nc2ToNc3AppModel
{

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

//	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Announcement'];
	/*
 *
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate()
	{
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Announcement Migration start.'));

		/* @var $Nc2Announcement AppModel */
		$Nc2Announcement = $this->getNc2Model('announcement');
		$nc2Announcement = $Nc2Announcement->find('all');


		// block_idをキーにFrameの移行を実施
			foreach ($nc2Announcement as $key )  {

				$nc2AnnouncementBlockld = $key['Nc2Announcement']['block_id'];
				/* @var $nc2ToNc3Frame Nc2ToNc3Frame */
				$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
				$nc3Frame = $Nc2ToNc3Frame->generateFrame($nc2AnnouncementBlockld);

				$data = [
					'status' => '1',
					'content' => $key['Nc2Announcement']['content'],
				];

				$nc3RoomId = $nc3Frame['Frame']['room_id'];
				//SAVE前にCurrentのデータを書き換えが必要なため
				Current::write('Room.id', $nc3RoomId);
				CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

				$Announcement = ClassRegistry::init('Announcements.Announcement');
				$aaa = $Announcement->saveAnnouncement($data);

				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
				Current::remove('Room.id', $nc3RoomId);


				var_dump('OK');exit;
				}


	}
}

