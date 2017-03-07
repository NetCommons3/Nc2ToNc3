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
		$Block = ClassRegistry::init('Blocks.Block');
		$Topic = ClassRegistry::init('Topics.Topic');
		foreach ($nc2Announcements as $nc2Announcement) {
			$nc2AnnounceBlockld = $nc2Announcement['Nc2Announcement']['block_id'];
			$nc3Frame = $Nc2ToNc3Frame->getMap($nc2AnnounceBlockld);
			if (!$nc3Frame) {
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

			//Announcement テーブルの移行を実施
			//SAVE前にCurrentのデータを書き換えが必要なため
			Current::write('Plugin.key', 'announcements');
			Current::write('Room.id', $nc3RoomId);

			CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;

			$Announcement->create();
			$Block->create();
			$Topic->create();

			if (!$Announcement->saveAnnouncement($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
				// ここでrollback
				$Announcement->rollback();

				$message = $this->getLogArgument($nc2Announcement) . "\n" .
					var_export($Announcement->validationErrors, true);
				$this->writeMigrationLog($message);

				continue;
			}

			unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
			Current::remove('Room.id', $nc3RoomId);
			Current::remove('Plugin.key', 'announcements');

		}
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

}

