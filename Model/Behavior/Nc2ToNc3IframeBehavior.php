<?php
/**
 * Nc2ToNc3IframeBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

/**
 * Nc2ToNc3IframeBehavior
 *
 */
class Nc2ToNc3IframeBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Get Log argument.
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Iframe Array data of Nc2Iframe.
 * @return string Log argument
 */
	public function getLogArgument(Model $model, $nc2Iframe) {
		return $this->__getLogArgument($nc2Iframe);
	}

/**
 * Generate Nc3Iframe data.
 *
 * Data sample
 * data[Iframe][id]:
 * data[Iframe][key]:
 * data[Iframe][url]:
 * data[Iframe][created_user]:
 * data[Iframe][created]:
 * data[IframeFrameSetting][id]:0
 * data[IframeFrameSetting][height]:0
 * data[IframeFrameSetting][display_scrollbar]:0
 * data[IframeFrameSetting][display_frame]:0
 * data[IframeFrameSetting][created_user]:0
 * data[IframeFrameSetting][created]:0
 *
 * @param Model $model Model using this behavior.
 * @param array $nc2Iframe Nc2Iframe data.
 * @return array Nc3Iframe data.
 */
	public function generateNc3IframeData(Model $model, $nc2Iframe) {
        /* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
        $Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
		$nc2BlockId = $nc2Iframe['Nc2Iframe']['block_id'];
        $frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
        if (!$frameMap) {
            $message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2Iframe));
            $this->_writeMigrationLog($message);
            return [];
        }

		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
        $data['Frame'] = [
            'id' => $frameMap['Frame']['id'],
        ];
        $data['Block'] = [
            'id' => '',
            'key' => '',
            'room_id' => $frameMap['Frame']['room_id'],
            'plugin_key' => 'iframes',
            'name' => $nc2Iframe['Nc2Iframe']['url'],
            'public_type' => 1,
        ];
		$data['Iframe'] = [
			'id' => '',
			'key' => '',
			'url' => $nc2Iframe['Nc2Iframe']['url'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Iframe['Nc2Iframe']),
			'created' => $this->_convertDate($nc2Iframe['Nc2Iframe']['insert_time']),
		];
		$data['IframeFrameSetting'] = [
			'id' => '',
			'height' => $nc2Iframe['Nc2Iframe']['frame_height'],
			'display_scrollbar' => $nc2Iframe['Nc2Iframe']['scrollbar_show'],
			'display_frame' => $nc2Iframe['Nc2Iframe']['scrollframe_show'],
			'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Iframe['Nc2Iframe']),
			'created' => $this->_convertDate($nc2Iframe['Nc2Iframe']['insert_time']),
		];

		return $data;
	}
}
