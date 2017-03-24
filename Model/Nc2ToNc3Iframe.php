<?php
/**
 * Nc2ToNc3Iframe
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Iframe
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
 * @method string convertChoiceValue($nc2Value, $nc3Choices)
 * @method string convertTitleIcon($titleIcon)
 * @method string convertTimezone($timezoneOffset)
 *
 * @see Nc2ToNc3IframeBehavior
 * @method string getLogArgument($nc2Iframe)
 * @method array generateNc3IframeData($nc2Iframe)
 *
 */
class Nc2ToNc3Iframe extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Iframe'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Iframe Migration start.'));

		/* @var $Nc2Iframe AppModel */
		$Nc2Iframe = $this->getNc2Model('iframe');
		$nc2Iframes = $Nc2Iframe->find('all');
		if (!$this->__saveIframeFromNc2($nc2Iframes)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Iframe Migration end.'));

		return true;
	}

/**
 * Save Iframe from Nc2.
 *
 * @param array $nc2Iframes Nc2Iframe data.
 * @return bool true on success
 * @throws Exception
 */
	private function __saveIframeFromNc2($nc2Iframes) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  Iframe data Migration start.'));

		/* @var $Frame Frame */
		/* @var $Iframe Iframe */
		/* @var $IframeFrameSetting IframeFrameSetting */
		/* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
		$Frame = ClassRegistry::init('Frames.Frame');
		$Iframe = ClassRegistry::init('Iframes.Iframe');
		$IframeFrameSetting = ClassRegistry::init('Iframes.IframeFrameSetting');
		$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		foreach ($nc2Iframes as $nc2Iframe) {
			$Iframe->begin();
			try {
				$data = $this->generateNc3IframeData($nc2Iframe);
				if (!$data) {
					$Iframe->rollback();
					continue;
				}

				$nc2BlockId = $nc2Iframe['Nc2Iframe']['block_id'];
				$frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
				$this->__writeCurrent($frameMap, 'iframes');

				$Frame->create();
				if (!$Iframe->saveIframe($data)) {
					$message = $this->getLogArgument($nc2Iframe) . "\n" .
						var_export($Iframe->validationErrors, true);
					$this->writeMigrationLog($message);

					$Iframe->rollback();
					continue;
				}

				$nc3Iframe = $Iframe->read();
				$frameSettingData = [
					'IframeFrameSetting' =>
						$data['IframeFrameSetting'] + [
							'frame_key' => $nc3Iframe['Iframe']['key'],
						],
				];
				if (!$IframeFrameSetting->saveIframeFrameSetting($frameSettingData)) {
					$message = $this->getLogArgument($nc2Iframe) . "\n" .
						var_export($Iframe->validationErrors, true);
					$this->writeMigrationLog($message);

					$Iframe->rollback();
					continue;
				}

				// 登録処理で使用しているデータを空に戻す
				$nc3RoomId = $frameMap['Frame']['room_id'];
				unset(CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value']);

				$nc2IframeId = $nc2Iframe['Nc2Iframe']['block_id'];
				$idMap = [
					$nc2IframeId => $Iframe->id,
				];
				$this->saveMap('Iframe', $idMap);

				$Iframe->commit();

			} catch (Exception $ex) {
				$Iframe->rollback($ex);
				throw $ex;
			}
		}

		$this->__removeUseCurrent();

		$this->writeMigrationLog(__d('nc2_to_nc3', '  Iframe data Migration end.'));

		return true;
	}

/**
 * Write Current.
 *
 * @param array $frameMap array data.
 * @param string $pluginKey plugin key.
 * @return void
 * @throws Exception
 */
	private function __writeCurrent($frameMap, $pluginKey) {
		$nc3RoomId = $frameMap['Frame']['room_id'];
		Current::write('Frame.key', $frameMap['Frame']['key']);
		Current::write('Frame.room_id', $frameMap['Frame']['room_id']);
		Current::write('Frame.plugin_key', $pluginKey);

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
		Current::write('Plugin.key', $pluginKey);

		// @see https://github.com/NetCommons3/Workflow/blob/3.1.0/Model/Behavior/WorkflowBehavior.php#L171-L175
		Current::write('Room.id', $nc3RoomId);
		CurrentBase::$permission[$nc3RoomId]['Permission']['content_publishable']['value'] = true;
	}

/**
 * Remove Current.
 *
 * @return void
 * @throws Exception
 */
	private function __removeUseCurrent() {
		// 登録処理で使用しているデータを空に戻す
		Current::remove('Frame.key');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Plugin.key');
		Current::remove('Room.id');
	}
}
