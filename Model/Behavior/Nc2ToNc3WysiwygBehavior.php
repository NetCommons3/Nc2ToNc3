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
App::uses('File', 'Utility');

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

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfTitleIcon($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfBaseUrlLink($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfCabinetFile($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfTex($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfTable($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$content = str_replace($searches, $replaces, $content);
		return $content;
	}

/**
 * Get str_replace arguments of download action.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfDownloadAction($content) {
		$strReplaceArguments = [];

		// save〇〇に渡すデータを、WysiwygBehavior::REPLACE_BASE_URL（{{__BASE_URL__}}）にすると、
		// HTMLPurifierで除去される（詳細箇所については未調査）
		// なので、WysiwygBehavior::beforeSave で置換される文字列にしとく
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Model/Behavior/WysiwygBehavior.php#L83
		//$replace = WysiwygBehavior::REPLACE_BASE_URL . './?action=common_download_main&upload_id=';
		$replaceUrl = Router::url('/', true);

		$pattern = '/(src|href)="\.\/(\?|index\.php\?)action=common_download_main&(?:amp;)?upload_id=(\d+)"/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$nc3UploadFile = $this->__saveUploadFileFromNc2($match[3]);
			if (!$nc3UploadFile) {
				// エラー処理どうする？とりあえず継続しとく。
				continue;
			}

			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Controller/WysiwygFileController.php#L107
			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Config/routes.php#L11-L19
			$controller = 'file';
			$size = '';
			$class = '';
			if ($match[1] === 'src') {
				$controller = 'image';
				$size = '/' . $this->__getImageSize($nc3UploadFile);
				$class = 'class="img-responsive nc3-img nc3-img-block" ';
			}

			$strReplaceArguments[0][] = $match[0];

			$strReplaceArguments[1][] = $class . $match[1] . '="' .
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
		// @see https://github.com/NetCommons3/Files/blob/3.1.0/Model/UploadFile.php#L260-L263
		$CakeFile = new File($fileData['tmp_name']);
		$data = $UploadFile->registByFile($CakeFile, 'wysiwyg', null, 'Wysiwyg.file', $nc3UploadFile);
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

/**
 * Get str_replace arguments of title icon.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfTitleIcon($content) {
		$strReplaceArguments = [];

		// PCRE_UNGREEDYパターン修飾子と.*?のどっちが良いのかわからんので、とりあえず.*?
		// あと、style属性はそのままにしとく。
		$pattern = '/src=".*?\/images\/comp\/textarea\/((?:titleicon|smiley)\/.*?\.gif)"/';

		// src属性のURLに Router::url('/') を使用している。
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/View/Helper/WysiwygHelper.php#L92
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/View/Helper/WysiwygHelper.php#L152-L162
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/webroot/js/plugins/titleicons/plugin.js#L25-L32
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/webroot/js/plugins/titleicons/plugin.js#L52-L56
		$prefixPath = $this->__getSubDirectory();

		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$strReplaceArguments[0][] = $match[0];

			// class属性は最後になっているが、挿入された結果が先頭になっているので、src属性の前に設定
			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/webroot/js/plugins/titleicons/plugin.js#L55
			$strReplaceArguments[1][] = 'class="nc-title-icon" src=' .
				'"' . $this->_convertTitleIcon($match[1], $prefixPath) . '"';
		}

		return $strReplaceArguments;
	}

/**
 * Get sub directory
 *
 * @return string sub directory
 */
	private function __getSubDirectory() {
		/* @var $RequestObject CakeRequest */
		$RequestObject = Router::getRequest();

		if (!$RequestObject->requested) {
			// Consoleから CakeObject::requestAction で呼び出しているので、CakeRequest::requested で判断
			// Consoleで呼び出される判断方法はほかにあるかも。
			return Router::url('/');
		}

		// Consoleで実行すると Router::url('/') で問題発生
		// @see Nc2ToNc3Shell::main
		return Router::url('/');
	}

/**
 * Get str_replace arguments of page link.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfBaseUrlLink($content) {
		$strReplaceArguments = [];

		/* @var $Nc2ToNc3 Nc2ToNc3 */
		/* @var $Nc2ToNc3Page Nc2ToNc3Page */
		$Nc2ToNc3 = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3');
		$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');

		$nc2BaseUrl = Hash::get($Nc2ToNc3->data, ['Nc2ToNc3', 'base_url']);
		$nc2BaseUrl = preg_quote($nc2BaseUrl, '/');
		$replaceBaseUrl = Router::url('/', true);

		// @see https://regexper.com/#%2Fhref%3D(http%3A%5C%2F%5C%2Flocalhost%5C%2F%7C%5C.%5C%2F)(.*%3F)%22%2F
		$pattern = '/href="(' . $nc2BaseUrl . '\/|\.\/)(.*?)"/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$replacePath = $match[2];

			preg_match('/page_id=(\d+)/', $replacePath, $pageIdMatches);
			if ($pageIdMatches) {
				$pageMap = $Nc2ToNc3Page->getMap($pageIdMatches[1]);
				$replacePath = $pageMap['Page']['permalink'];
			}

			$strReplaceArguments[0][] = $match[0];
			$strReplaceArguments[1][] = 'href="' . $replaceBaseUrl . $replacePath . '"';
		}

		return $strReplaceArguments;
	}

/**
 * Get str_replace arguments of cabinet file.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfCabinetFile($content) {
		$strReplaceArguments = [];

		// cabinet_action_main_downloadアクションの処理
		// 以下、NC1からの移行処理を参考
		/*
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

		return $strReplaceArguments;
	}

/**
 * Get str_replace arguments of TeX.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfTex($content) {
		$strReplaceArguments = [];

		$pattern = '/<img .*? src=".*?action=common_tex_main&amp;c=(.*?)" .*?\/>/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$strReplaceArguments[0][] = $match[0];

			$texValue = str_replace("%_", "%", $match[1]);
			$texValue = rawurldecode($texValue);

			$strReplaceArguments[1][] =
				'<span class="tex-char">' .
				'$$' . $texValue . '$$' .
				'</span>';
		}

		return $strReplaceArguments;
	}

/**
 * Get str_replace arguments of Table.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfTable($content) {
		$strReplaceArguments = [];

		// <table>のstyleをclassに置き換え
		$patterns[] = [
			'pattern' => '/<table.*? (style=".*?")>/',
			'replace' => 'class="table table-bordered table-responsive"'
		];
		// <tr><td>のstyle消す
		//		$patterns[] = [
		//			'pattern' => '/<tr.*?( style=".*?")>/',
		//			'replace' => ''
		//		];
		//		$patterns[] = [
		//			'pattern' => '/<td.*?( style=".*?")>/',
		//			'replace' => ''
		//		];
		foreach ($patterns as $pattern) {
			preg_match_all($pattern['pattern'], $content, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {

				$replaceTable = str_replace($match[1], $pattern['replace'], $match[0]);
				$strReplaceArguments[0][] = $match[0];
				$strReplaceArguments[1][] = $replaceTable;
			}
		}

		return $strReplaceArguments;
	}

}
