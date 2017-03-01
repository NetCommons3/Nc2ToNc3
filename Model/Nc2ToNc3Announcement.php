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
 * @method generateFrame($nc2AnnounceBlockld, $nc3FramePluginKey, $nc3FramesLangName)
 * @method Model saveAnnouncement($data)
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
		$nc2Announcement = $Nc2Announcement->find('all');

		// block_idをキーにFrameの移行を実施。3以下はデフォルトのため、移行しない
		foreach ($nc2Announcement as $key) {
			if ($key['Nc2Announcement']['block_id'] <= 3) {
				continue;
			} else {
				$nc2AnnounceBlockld = $key['Nc2Announcement']['block_id'];
			}

			$nc3FramePluginKey = 'announcements';
			$nc3FramesLangName = 'お知らせ';

			/* @var $nc2ToNc3Frame Nc2ToNc3Frame */
			$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
			$nc3Frame = $Nc2ToNc3Frame->generateFrame($nc2AnnounceBlockld, $nc3FramePluginKey, $nc3FramesLangName);
			$nc3RoomId = $nc3Frame['Frame']['room_id'];

			$data = [
				'Announcement' => [
					'status' => '1',
					'content' => $key['Nc2Announcement']['content'],
				],
				'Block' => [
					'room_id' => $nc3RoomId,
					'plugin_key' => 'blocks'
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

			$Nc2ToNc3Announcement = ClassRegistry::init('Announcements.Announcement');
			$Nc2ToNc3Block = ClassRegistry::init('Blocks.Block');
			$Nc2ToNc3Topic = ClassRegistry::init('Topics.Topic');
			$Nc2ToNc3Announcement->create();
			$Nc2ToNc3Block->create();
			$Nc2ToNc3Topic->create();

			if (!$Nc2ToNc3Announcement->saveAnnouncement($data)) {
				// 各プラグインのsave○○にてvalidation error発生時falseが返ってくるがrollbackしていないので、
				// ここでrollback
				$Nc2ToNc3Announcement->rollback();
				return false;
			}

			unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);
			Current::remove('Room.id', $nc3RoomId);
			Current::remove('Plugin.key', 'announcements');

		}
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Announcement Migration end.'));
		return true;
	}
}

