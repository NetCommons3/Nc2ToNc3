<?php
/**
 * Nc2ToNc3WysiwygBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('WysiwygBehavior', 'Wysiwyg.Model/Behavior');

/**
 * Nc2ToNc3WysiwygBehavior
 *
 */
class Nc2ToNc3WysiwygBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Convert nc2 content.
 *
 * @param Model $model Model using this behavior
 * @param string $content Nc2 content.
 * @return string converted nc3 body.
 */
	public function convertWYSIWYG(Model $model, $content) {
		$searches = [];
		$replaces = [];

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfDownloadAction($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$content = str_replace($searches, $replaces, $content);

		return $content;

		/*
		$replace = './images/comp/textarea/';
		$pattern = '/["\']&XOOPS_URL;\/images\/icon\/(\S*)["\']/';
		preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$lastCharacter = substr($match[1], -1);
			if ($lastCharacter == '"' || $lastCharacter == '\'') {
				$match[0] = substr($match[0], 0, -1);
				$match[1] = substr($match[1], 0, -1);
			}

			if (in_array($match[0], $searches)) {
				continue;
			}

			$searches[] = $match[0];
			$replaces[] = '"' . $replace . Utility::convertIconPath($match[1]) . '"';
		}

		$page =& Page::getInstance();
		$replace = './?action=pages_view_main&page_id=';
		$pattern = '/["\']&XOOPS_URL;\/modules\/menu\/main\.php\?page_id=(\S*)&op=change_page["\']/';
		preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$lastCharacter = substr($match[1], -1);
			if ($lastCharacter == '"' || $lastCharacter == '\'') {
				$match[0] = substr($match[0], 0, -1);
				$match[1] = substr($match[1], 0, -1);
			}

			if (in_array($match[0], $searches)) {
				continue;
			}

			$searches[] = $match[0];

			$match[1] = urldecode($match[1]);

			$pageAssociation = $page->getPageAssociation($match[1]);

			if (empty($pageAssociation)) {
				$replaces[] = '"' . $replace . '"';
			} else {
				$replaces[] = '"' . $replace . $pageAssociation['pageID'] . '"';
			}
		}

		$cabinet =& Cabinet::getInstance();
		$block =& Block::getInstance();
		$replace1 = './?action=pages_view_main&active_action=cabinet_view_main_init&folder_id=';
		$replace2 = '&block_id=';
		$replace3 = '#_';
		$pattern = '/["\']&XOOPS_URL;\/modules\/cabinet\/cabinet_main\.php\?block_id=(\S*)&folder_id=(\S*)#(\S*)["\']/';
		preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$replace = '';
			$lastCharacter = substr($match[3], -1);
			if ($lastCharacter == '"' || $lastCharacter == '\'') {
				$match[0] = substr($match[0], 0, -1);
				$match[3] = substr($match[3], 0, -1);
			}

			if (in_array($match[0], $searches)) {
				continue;
			}

			$searches[] = $match[0];

			$match[1] = urldecode($match[1]);
			$match[2] = urldecode($match[2]);
			$match[3] = urldecode($match[3]);

			$cabinetAssociation = $cabinet->getAssociation($match[2]);
			$blockAssociation = $block->getAssociation($match[1]);
			if (empty($cabinetAssociation) || empty($blockAssociation)) {
				$replace = '"' . $replace1 . $replace2 . $replace3 . '"';
			} else {
				$replace = '"' . $replace1 . $cabinetAssociation['fileID']
				. $replace2 . $blockAssociation['blockID']
				. $replace3 . $blockAssociation['blockID'] . '"';
			}

			$replaces[] = $replace;
		}

		$searches[] = '&XOOPS_URL;/include/comp/textarea/tex.php?';
		$replaces[] = './?action=common_tex_main&';

		$searches[] = '&XOOPS_URL;';
		$replaces[] = './nc1Files';

		$str = str_replace($searches, $replaces, $str);

		return $str;

		$body = $content;

		return $body;*/
	}

/**
 * Convert nc2 content.
 *
 * @param string $content Nc2 content.
 * @return string converted nc3 body.
 */
	private function __getStrReplaceArgumentsOfDownloadAction($content) {
		$strReplaceArguments = [];

		// save〇〇に渡すデータを、WysiwygBehavior::REPLACE_BASE_URL（{{__BASE_URL__}}）にすると、
		// HTMLPurifierで除去される（詳細箇所については未調査）
		// なので、WysiwygBehavior::beforeSave で置換される文字列にしとく
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Model/Behavior/WysiwygBehavior.php#L83
		//$replace = WysiwygBehavior::REPLACE_BASE_URL . './?action=common_download_main&upload_id=';
		$replaceUrl = Router::url('/', true);

		$pattern = '/(src|href)="\.\/\?action=common_download_main&(?:amp;)?+upload_id=(\d+)"/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$nc3UploadFile = $this->__saveUploadFileFromNc2($match[2]);
			if (!$nc3UploadFile) {
				// エラー処理どうする？とりあえず継続しとく。
				continue;
			}

			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Controller/WysiwygFileController.php#L107
			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Config/routes.php#L11-L19
			$controller = 'file';
			$size = '';
			if ($match[1] === 'src') {
				$controller = 'image';
				$size = '/' . $this->__getImageSize($nc3UploadFile);
			}

			$strReplaceArguments[0][] = $match[0];

			$strReplaceArguments[1][] = $match[1] . '="' .
				$replaceUrl . 'wysiwyg/' . $controller . '/download' .
				'/' . $nc3UploadFile['UploadFile']['room_id'] .
				'/' . $nc3UploadFile['UploadFile']['id'] .
				$size . '"';
		}

		return $strReplaceArguments;
	}

/**
 * Save UploadFile from Nc2.
 *
 * @param string $nc2UploadId Nc2Upload.id.
 * @return array Nc3UploadFile data.
 */
	private function __saveUploadFileFromNc2($nc2UploadId) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $UploadFile UploadFile */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$UploadFile = ClassRegistry::init('Files.UploadFile');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('UploadFile', $nc2UploadId);
		if ($mapIdList) {
			return $UploadFile->findById($mapIdList[$nc2UploadId], null, null, -1);
		}

		/* @var $nc2ToNc3Upload Nc2ToNc3Upload */
		$nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
		$fileData = $nc2ToNc3Upload->generateUploadFile($nc2UploadId);
		if (!$fileData) {
			return $fileData;
		}

		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$nc2Upload = $nc2ToNc3Upload->getNc2UploadByUploadId($nc2UploadId);
		$roomMap = $Nc2ToNc3Room->getMap($nc2Upload['Nc2Upload']['room_id']);

		// Room.idの書き換え
		// @see https://github.com/NetCommons3/Files/blob/3.1.0/Model/UploadFile.php#L174-L176
		$nc3UploadFile['UploadFile'] = [
			'room_id' => $roomMap['Room']['id']
		];
		$contentRoomId = Current::read('Room.id');
		Current::write('Room.id', $roomMap['Room']['id']);

		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Controller/WysiwygFileController.php#L88
		$data = $UploadFile->registByFile($fileData['tmp_name'], 'wysiwyg', null, 'Wysiwyg.file', $nc3UploadFile);
		// Room.idを戻す
		Current::write('Room.id', $contentRoomId);
		if (!$data) {
			$message = __d('nc2_to_nc3', '%s not found .', 'Nc2Upload:' . $nc2UploadId);
			$this->_writeMigrationLog($message);

			return $data;
		}

		$idMap = [
			$nc2UploadId => $UploadFile->id
		];
		$this->_saveMap('UploadFile', $idMap);

		return $data;
	}

/**
 * Get image size.
 *
 * @param array $nc3UploadFile Nc3UploadFile data.
 * @return string image size('big','medium','small','thumb')
 */
	private function __getImageSize($nc3UploadFile) {
		/* @var $UploadFile UploadFile */
		$UploadFile = ClassRegistry::init('Files.UploadFile');
		$path = $UploadFile->getRealFilePath($nc3UploadFile);
		list($width, $height) = getimagesize($path);

		if ($width <= 80 && $height <= 80) {
			return 'thumb';
		}
		if ($width <= 200 && $height <= 200) {
			return 'small';
		}
		if ($width <= 400 && $height <= 400) {
			return 'medium';
		}

		return 'big';
	}

}
