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
 * @method void changeNc3CurrentLanguage($langDirName = null)
 * @method void restoreNc3CurrentLanguage()
 *
 */
class Nc2ToNc3Announcement extends Nc2ToNc3AppModel {

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
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Announcement Migration start.'));

		/* @var $Nc2Announcement AppModel */
		$Nc2Announcement = $this->getNc2Model('announcement');
		$nc2Announcements = $Nc2Announcement->find('all');

		/* @var $nc2ToNc3Frame Nc2ToNc3Frame */
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$Announcement = ClassRegistry::init('Announcements.Announcement');

		//BlockBehaviorがシングルトンで利用されるため、BlockBehavior::settingsを初期化
		//@see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/BehaviorCollection.php#L128-L133
		$Announcement->Behaviors->Block->settings = $Announcement->actsAs['Blocks.Block'];

		$Block = ClassRegistry::init('Blocks.Block');
		$BlocksLanguage = ClassRegistry::init('Blocks.BlocksLanguage');
		$Topic = ClassRegistry::init('Topics.Topic');
		foreach ($nc2Announcements as $nc2Announcement) {
			$Announcement->begin();

			$nc2Blockld = $nc2Announcement['Nc2Announcement']['block_id'];
			$nc3Frame = $Nc2ToNc3Frame->getMap($nc2Blockld);
			if (!$nc3Frame) {
				$Announcement->rollback();
				continue;
			}

			$AnnouncementMap = $this->__getMap($nc2Blockld);
			if ($AnnouncementMap) {
				// 移行済み
				$Announcement->rollback();
				continue;
			}

			$nc3RoomId = $nc3Frame['Frame']['room_id'];
			$data = [
				'Announcement' => [
					'status' => '1',
					'content' => $nc2Announcement['Nc2Announcement']['content']
				],
				'Block' => [
					'room_id' => $nc3RoomId,
					'plugin_key' => 'announcements'
				],
				'Frame' => [
					'id' => $nc3Frame['Frame']['id']
				],
				'Topic' => [
					'plugin_key' => 'announcements'
				]
			];

			if ($AnnouncementMap) {
				$data['Announcement']['id'] = $AnnouncementMap['Announcement']['id'];
				$data['Announcement']['block_id'] = $AnnouncementMap['Announcement']['block_id'];
				// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L345
				$data['Announcement']['key'] = $AnnouncementMap['Announcement']['key'];

				$data['Block']['id'] = $AnnouncementMap['Announcement']['block_id'];
				$data['Block']['key'] = $AnnouncementMap['Block']['key'];
			}

			//Announcement テーブルの移行を実施
			//SAVE前にCurrentのデータを書き換えが必要なため
			Current::write('Plugin.key', 'announcements');
			Current::write('Room.id', $nc3RoomId);

			CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

			// Model::idを初期化しないとUpdateになってしまう。
			$Announcement->create();
			$Block->create();
			$BlocksLanguage->create();
			$Topic->create();

			if (!$Announcement->saveAnnouncement($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
				// ここでrollback
				$Announcement->rollback();

				$message = $this->getLogArgument($nc2Announcement) . "\n" .
					var_export($Announcement->validationErrors, true);
				$this->writeMigrationLog($message);

				$Announcement->rollback();
				continue;
			}

			unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

			$idMap = [
				$nc2Blockld => $Announcement->id
			];
			$this->saveMap('Announcement', $idMap);

			$Announcement->commit();
		}

		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Announcement Migration end.'));
		return true;
	}

/**
 * Get Log argument.
 *
 * @param array $nc2Announcement Nc2Announcement data
 * @return string Log argument
 */
	public function getLogArgument($nc2Announcement) {
		return 'Nc2Announcement ' .
			'block_id:' . $nc2Announcement['Nc2Announcement']['block_id'];
	}

/**
 * Get map
 *
 * @param array|string $nc2Blocklds Nc2Announcement block_id.
 * @return array Map data with Nc2Announcement block_id as key.
 */
	private function __getMap($nc2Blocklds) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $Announcement Announcement */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$Announcement = ClassRegistry::init('Announcements.Announcement');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Announcement', $nc2Blocklds);
		$query = [
			'fields' => [
				'Announcement.id',
				'Announcement.block_id',
				'Announcement.key',
				'Block.key',
			],
			'conditions' => [
				'Announcement.id' => $mapIdList
			],
			'recursive' => 0,
		];
		$nc3Announcements = $Announcement->find('all', $query);
		if (!$nc3Announcements) {
			return $nc3Announcements;
		}

		$map = [];
		foreach ($nc3Announcements as $nc3Announcement) {
			$nc2Id = array_search($nc3Announcement['Announcement']['id'], $mapIdList);
			$map[$nc2Id] = $nc3Announcement;
		}

		if (is_string($nc2Blocklds)) {
			$map = $map[$nc2Blocklds];
		}

		return $map;
	}

}

