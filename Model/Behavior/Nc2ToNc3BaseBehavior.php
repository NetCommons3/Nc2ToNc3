<?php
/**
 * Nc2ToNc3BaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('ModelBehavior', 'Model');
App::uses('Nc2ToNc3', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3MigrationBehavior
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Nc2ToNc3BaseBehavior extends ModelBehavior {

/**
 * Language id from Nc2.
 *
 * @var array
 */
	private $__languageIdFromNc2 = null;

/**
 * Language list.
 *
 * @var array
 */
	private $__languageList = null;

/**
 * Nc3Language data.
 *
 * @var array
 */
	private $__nc3CurrentLanguage = null;

/**
 * Setup this behavior with the specified configuration settings.
 *
 * @param Model $model Model using this behavior
 * @param array $config Configuration settings for $model
 * @return void
 */
	public function setup(Model $model, $config = array()) {
		// Nc2ToNc3BaseBehavior::_writeMigrationLogでログ出力している
		// CakeLog::writeでファイルとコンソールに出力していた。
		// Consoleに出力すると<tag></tag>で囲われ見辛い。
		// @see
		// https://github.com/cakephp/cakephp/blob/2.9.4/lib/Cake/Console/ConsoleOutput.php#L230-L241
		// CakeLog::infoをよびだし、debug.logとNc2ToNc3.logの両方出力するようにした。
		CakeLog::config(
			'Nc2ToNc3File',
			[
				'engine' => 'FileLog',
				'types' => ['info'],
				'scopes' => ['Nc2ToNc3'],
				'file' => 'Nc2ToNc3.log',
			]
		);
	}

/**
 * Write migration log.
 *
 * @param Model $model Model using this behavior.
 * @param string $message Migration message.
 * @return void
 */
	public function writeMigrationLog(Model $model, $message) {
		$debugString = '';
		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
		if (isset($backtraces[4]) &&
			isset($backtraces[4]['line']) &&
			isset($backtraces[4]['class']) &&
			$backtraces[4]['function'] == 'writeMigrationLog'
		) {
			$debugString = $backtraces[4]['class'] . ' on line ' . $backtraces[4]['line'];
		}

		$this->_writeMigrationLog($message, $debugString);
	}

/**
 * Get Nc2 Model.
 *
 * @param Model $model Model using this behavior.
 * @param string $tableName Nc2 table name.
 * @return Model Nc2 model
 */
	public function getNc2Model(Model $model, $tableName) {
		return $this->_getNc2Model($tableName);
	}

/**
 * Get languageId from Nc2.
 *
 * @param Model $model Model using this behavior.
 * @return string LanguageId from Nc2.
 */
	public function getLanguageIdFromNc2(Model $model) {
		return $this->_getLanguageIdFromNc2();
	}

/**
 * Convert nc2 date.
 *
 * @param Model $model Model using this behavior.
 * @param string $date Nc2 date.
 * @return Model converted date.
 */
	public function convertDate(Model $model, $date) {
		return $this->_convertDate($date);
	}

/**
 * Convert nc2 lang_dirname.
 *
 * @param Model $model Model using this behavior.
 * @param string $langDirName nc2 lang_dirname.
 * @return string converted nc2 lang_dirname.
 */
	public function convertLanguage(Model $model, $langDirName) {
		return $this->_convertLanguage($langDirName);
	}

/**
 * Save Nc2ToNc3Map
 *
 * @param Model $model Model using this behavior.
 * @param string $modelName Model name
 * @param array $idMap Nc2ToNc3Map.nc3_id with Nc2ToNc3Map.nc2_id as key.
 * @return void
 */
	public function saveMap(Model $model, $modelName, $idMap) {
		$this->_saveMap($modelName, $idMap);
	}

/**
 * Get map.
 *
 * 継承したクラスの_getMapメソッドを呼び出す
 *
 * @param Model $model Model using this behavior.
 * @param array|string $nc2Ids Nc2 id.
 * @return string Id map.
 */
	public function getMap(Model $model, $nc2Ids = null) {
		return $this->_getMap($nc2Ids);
	}

/**
 * Change nc3 current language data
 *
 * @param Model $model Model using this behavior.
 * @param string $langDirName nc2 lang_dirname.
 * @return void
 */
	public function changeNc3CurrentLanguage(Model $model, $langDirName = null) {
		$this->_changeNc3CurrentLanguage($langDirName);
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	public function restoreNc3CurrentLanguage() {
		$this->_restoreNc3CurrentLanguage();
	}

/**
 * Convert nc2 display_days.
 *
 * @param Model $model Model using this behavior.
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	public function convertDisplayDays(Model $model, $displayDays) {
		return $this->_convertDisplayDays($displayDays);
	}

/**
 * Convert nc2 choice value
 *
 * @param Model $model Model using this behavior.
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted nc3 value.
 */
	public function convertChoiceValue(Model $model, $nc2Value, $nc3Choices) {
		return $this->_convertChoiceValue($nc2Value, $nc3Choices);
	}

/**
 * Convert nc2 title_icon.
 *
 * @param Model $model Model using this behavior.
 * @param string $titleIcon Nc2 title_icon.
 * @return string converted nc3 title_icon.
 */
	public function convertTitleIcon(Model $model, $titleIcon) {
		return $this->_convertTitleIcon($titleIcon);
	}

/**
 * Convert nc2 timezone_offset.
 *
 * @param Model $model Model using this behavior.
 * @param string $timezoneOffset Nc2 timezone_offset.
 * @return string converted nc3 timezone.
 */
	public function convertTimezone(Model $model, $timezoneOffset) {
		return $this->_convertTimezone($timezoneOffset);
	}

/**
 * Write migration log.
 *
 * @param string $message Migration message.
 * @param string $debugString Debug string.
 * @return void
 */
	protected function _writeMigrationLog($message, $debugString = '') {
		if ($debugString) {
			CakeLog::info($message . ' : ' . $debugString, ['Nc2ToNc3']);
			return;
		}

		$backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		if (isset($backtraces[0]) &&
			isset($backtraces[0]['line']) &&
			isset($backtraces[1]['class']) &&
			$backtraces[0]['function'] == '_writeMigrationLog'
		) {
			$message = $message . ' : ' . $backtraces[1]['class'] . ' on line ' . $backtraces[0]['line'];
		}

		CakeLog::info($message, ['Nc2ToNc3']);
	}

/**
 * Get Nc2 Model.
 *
 * @param string $tableName Nc2 table name.
 * @return Model Nc2 model.
 */
	protected function _getNc2Model($tableName) {
		// クラス自体は存在しない。
		// Nc2ToNc3AppModelのインスタンスを作成し返す。
		// Nc2ToNc3AppModelはNetCommonsAppModelを継承しない。
		$Molde = ClassRegistry::init([
			'class' => 'Nc2ToNc3.Nc2' . $tableName,
			'table' => $tableName,
			'alias' => 'Nc2' . Inflector::classify($tableName),
			'ds' => Nc2ToNc3::CONNECTION_NAME
		]);

		return $Molde;
	}

/**
 * Get languageId from Nc2.
 *
 * @return string LanguageId from Nc2.
 */
	protected function _getLanguageIdFromNc2() {
		// Model毎にInstanceが作成されるため、Model毎にNc2Configから読み込まれる
		// 今のところ、UserAttributeとUserだけなので、Propertyで保持するが、
		// 増えてきたらstatic等でNc2Configから読み込まないよう変更する
		if (isset($this->__languageIdFromNc2)) {
			return $this->__languageIdFromNc2;
		}

		/* @var $Nc2Config AppModel */
		$Nc2Config = $this->_getNc2Model('config');
		$configData = $Nc2Config->findByConfName('language', 'conf_value', null, -1);

		$language = $configData['Nc2Config']['conf_value'];
		switch ($language) {
			case 'english':
				$code = 'en';
				break;

			default:
				$code = 'ja';

		}

		/* @var $Language Language */
		$Language = ClassRegistry::init('M17n.Language');
		$language = $Language->findByCode($code, 'id', null, -1);
		$this->__languageIdFromNc2 = $language['Language']['id'];

		return $this->__languageIdFromNc2;
	}

/**
 * Convert nc3 date.
 *
 * @param string $date Nc2 date.
 * @return Model converted date.
 */
	protected function _convertDate($date) {
		if (strlen($date) != 14) {
			return null;
		}

		// YmdHis → Y-m-d H:i:s　
		$date = substr($date, 0, 4) . '-' .
				substr($date, 4, 2) . '-' .
				substr($date, 6, 2) . ' ' .
				substr($date, 8, 2) . ':' .
				substr($date, 10, 2) . ':' .
				substr($date, 12, 2);

		return $date;
	}

/**
 * Convert nc2 lang_dirname.
 *
 * @param string $langDirName nc2 lang_dirname.
 * @return string converted nc2 lang_dirname.
 */
	protected function _convertLanguage($langDirName) {
		if (!$langDirName) {
			return null;
		}

		// Model毎にInstanceが作成されるため、Model毎にNc3Languageから読み込まれる
		// 今のところ、RoomとPageだけなので、Propertyで保持するが、
		// 増えてきたらstatic等でNc3Languageから読み込まないよう変更する
		// Nc2ToNc3LabuageというModelクラス作った方が良いかも。
		if (!isset($this->__languageList)) {
			/* @var $Language Language */
			$Language = ClassRegistry::init('M17n.Language');
			$query = [
				'fields' => [
					'Language.code',
					'Language.id'
				],
				'conditions' => [
					'is_active' => true
				],
				'recursive' => -1
			];
			$this->__languageList = $Language->find('list', $query);
		}

		$map = [
			'japanese' => 'ja',
			'english' => 'en',
			'chinese' => 'zh'
		];
		$code = $map[$langDirName];

		if (isset($this->__languageList[$code])) {
			return $this->__languageList[$code];
		}

		return null;
	}

/**
 * Save Nc2ToNc3Map
 *
 * @param string $modelName Model name
 * @param array $idMap Nc2ToNc3Map.nc3_id with Nc2ToNc3Map.nc2_id as key.
 * @return array Nc2ToNc3Map data.
 */
	protected function _saveMap($modelName, $idMap) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$data['Nc2ToNc3Map'] = [
			'model_name' => $modelName,
			'nc2_id' => key($idMap),
			'nc3_id' => current($idMap),
		];

		return $Nc2ToNc3Map->saveMap($data);
	}

/**
 * Change nc3 current language data
 *
 * @param string $langDirName nc2 lang_dirname.
 * @return void
 */
	protected function _changeNc3CurrentLanguage($langDirName = null) {
		$nc3LanguageId = null;
		if ($langDirName) {
			$nc3LanguageId = $this->_convertLanguage($langDirName);
		}
		if (!$nc3LanguageId) {
			$nc3LanguageId = $this->_getLanguageIdFromNc2();
		}

		/* @var $Language Language */
		$Language = ClassRegistry::init('M17n.Language');

		if (Current::read('Language.id') != $nc3LanguageId) {
			$this->__nc3CurrentLanguage = Current::read('Language');
			$language = $Language->findById($nc3LanguageId, null, null, -1);
			Current::write('Language', $language['Language']);
		}
	}

/**
 * Restore nc3 current language data
 *
 * @return void
 */
	protected function _restoreNc3CurrentLanguage() {
		if (isset($this->__nc3CurrentLanguage)) {
			Current::write('Language', $this->__nc3CurrentLanguage);
			unset($this->__nc3CurrentLanguage);
		}
	}

/**
 * Convert nc2 display_days.
 *
 * @param string $displayDays nc2 display_days.
 * @return string converted nc2 display_days.
 */
	protected function _convertDisplayDays($displayDays) {
		if (!$displayDays) {
			return null;
		}
		$arr = [30, 14, 7, 3, 1];
		foreach ($arr as $num) {
			if ($displayDays >= $num) {
				$displayDays = $num;
				break;
			}
		}
		return $displayDays;
	}

/**
 * Convert nc2 choice value.
 *
 * @param string $nc2Value Nc2 value.
 * @param array $nc3Choices Nc3 array choices
 * @return string converted nc3 value.
 */
	protected function _convertChoiceValue($nc2Value, $nc3Choices) {
		if (!$nc2Value) {
			return null;
		}

		$nc3Choices = rsort($nc3Choices, SORT_NUMERIC);
		foreach ($nc3Choices as $nc3Choice) {
			if ($nc2Value >= $nc3Choice) {
				$nc2Value = $nc3Choice;
				break;
			}
		}

		return $nc2Value;
	}

/**
 * Convert nc2 title_icon.
 *
 * @param string $titleIcon Nc2 title_icon.
 * @return string converted nc3 title_icon.
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
	protected function _convertTitleIcon($titleIcon) {
		$map = [
			'smiley/smiley-smile1.gif' => '40_010_smile.svg',
			'smiley/smiley-smile2.gif' => '40_011_laugh.svg',
			'smiley/smiley-smile3.gif' => '40_010_smile.svg',
			'smiley/smiley-smile4.gif' => '40_012_expect.svg',
			'smiley/smiley-smile5.gif' => '40_011_laugh.svg',
			'smiley/smiley-smile6.gif' => '40_011_laugh.svg',
			'smiley/smiley-smile7.gif' => '40_013_pleased.svg',
			'smiley/smiley-smile8.gif' => '40_013_pleased.svg',
			'smiley/smiley-smile9.gif' => '40_011_laugh.svg',
			'smiley/smiley-smile10.gif' => '40_014_joke.svg',
			'smiley/smiley-smile11.gif' => '40_015_excited.svg',
			'smiley/smiley-smile12.gif' => '40_015_excited.svg',
			'smiley/smiley-cryhappily1.gif' => '40_030_cried_for_joy.svg',
			'smiley/smiley-sweat1.gif' => '40_020_bewilderment.svg',
			'smiley/smiley-sweat2.gif' => '40_020_bewilderment.svg',
			'smiley/smiley-sweat3.gif' => '40_021_worry.svg',
			'smiley/smiley-sweat4.gif' => '40_022_trouble.svg',
			'smiley/smiley-forcedsmile1.gif' => '40_023_bitter_smile.svg',
			'smiley/smiley-forcedsmile2.gif' => '40_023_bitter_smile.svg',
			'smiley/smiley-forcedsmile3.gif' => '40_023_bitter_smile.svg',
			'smiley/smiley-despair1.gif' => '40_024_shock.svg',
			'smiley/smiley-cry1.gif' => '40_026_patience.svg',
			'smiley/smiley-cry2.gif' => '40_030_cry.svg',
			'smiley/smiley-cry3.gif' => '40_030_cry.svg',
			'smiley/smiley-cry4.gif' => '40_030_cried_for_joy.svg',
			'smiley/smiley-cry5.gif' => '40_030_cry.svg',
			'smiley/smiley-cry6.gif' => '40_030_cry.svg',
			'smiley/smiley-thanks1.gif' => '40_040_bow.svg',
			'smiley/smiley-hmm1.gif' => '40_010_smile.svg',
			'smiley/smiley-grin1.gif' => '40_016_gloat.svg',
			'smiley/smiley-anger1.gif' => '40_050_angry.svg',
			'smiley/smiley-anger2.gif' => '40_050_angry.svg',
			'smiley/smiley-lookconfused1.gif' => '40_010_smile.svg',
			'smiley/smiley-other1.gif' => '40_061_glasses.svg',
			'smiley/smiley-other2.gif' => '40_061_glasses.svg',
			'smiley/smiley-other3.gif' => '40_010_smile.svg',
			'smiley/smiley-other4.gif' => '40_010_smile.svg',
			'smiley/smiley-other5.gif' => '40_010_smile.svg',
			'smiley/smiley-other6.gif' => '40_010_smile.svg',
			'titleicon/icon-new.gif' => '10_010_new.svg',
			'titleicon/icon-weather1.gif' => '20_010_sunny.svg',
			'titleicon/icon-weather2.gif' => '20_011_cloudy.svg',
			'titleicon/icon-weather3.gif' => '20_012_party_cloudy.svg',
			'titleicon/icon-weather4.gif' => '20_013_rainy.svg',
			'titleicon/icon-weather5.gif' => '20_014_light_rain.svg',
			'titleicon/icon-weather6.gif' => '20_015_heavy_rain.svg',
			'titleicon/icon-weather7.gif' => '20_016_storm.svg',
			'titleicon/icon-weather8.gif' => '20_017_thunders.svg',
			'titleicon/icon-weather9.gif' => '20_018_snow.svg',
			'titleicon/icon-star1.gif' => '20_019_star.svg',
			'titleicon/icon-star2.gif' => '20_019_star.svg',
			'titleicon/icon-star3.gif' => '10_060_one_star.svg',
			'titleicon/icon-work.gif' => '30_040_business.svg',
			'titleicon/icon-businesstrip.gif' => '30_020_trip.svg',
			'titleicon/icon-train.gif' => '30_021_train.svg',
			'titleicon/icon-car.gif' => '30_022_car.svg',
			'titleicon/icon-important.gif' => '10_020_exclamation.svg',
			'titleicon/icon-deadline.gif' => '10_023_deadline.svg',
			'titleicon/icon-haste.gif' => '10_080_rush.svg',
			'titleicon/icon-announcement.gif' => '10_030_announce.svg',
			'titleicon/icon-ok1.gif' => '10_064_hanamaru.svg',
			'titleicon/icon-ok2.gif' => '10_072_roger.svg',
			'titleicon/icon-ok3.gif' => '10_011_ok.svg',
			'titleicon/icon-arrow1.gif' => '10_040_left.svg',
			'titleicon/icon-arrow2.gif' => '10_041_right.svg',
			'titleicon/icon-question.gif' => '10_022_question.svg',
			'titleicon/icon-headcount1.gif' => '40_010_smile.svg',
			'titleicon/icon-headcount2.gif' => '30_060_group.svg',
			'titleicon/icon-headcount3.gif' => '30_060_group.svg',
			'titleicon/icon-headcount4.gif' => '30_060_group.svg',
			'titleicon/icon-meeting.gif' => '30_041_meeting.svg',
			'titleicon/icon-flash.gif' => '30_061_light.svg',
			'titleicon/icon-sport.gif' => '30_011_sport.svg',
			'titleicon/icon-science.gif' => '30_010_science.svg',
			'titleicon/icon-music.gif' => '30_014_music.svg',
			'titleicon/icon-pc.gif' => '30_015_computer.svg',
			'titleicon/icon-meal.gif' => '30_017_lunch.svg',
			'titleicon/icon-movie.gif' => '30_016_audiovisual.svg',
			'titleicon/icon-anniversary.gif' => '30_031_anniversary.svg',
			'titleicon/icon-heart.gif' => '10_080_heart.svg',
			'titleicon/icon-excursion.gif' => '30_033_picnic.svg',
			'titleicon/icon-teabreak.gif' => '30_062_coffee.svg',
			'titleicon/icon-animal1.gif' => '10_081_pow.svg',
			'titleicon/icon-animal2.gif' => '10_081_pow.svg',
			'titleicon/icon-true.gif' => '10_044_circle.svg',
			'titleicon/icon-false.gif' => '10_045_cross.svg',
			'titleicon/icon-telephone.gif' => '30_050_mobile.svg',
			'titleicon/icon-brilliant.gif' => '10_082_glitter.svg',
			'titleicon/icon-bus.gif' => '30_024_bus.svg',
			'titleicon/icon-ambulance.gif' => '30_062_hospital.svg',
			'titleicon/icon-school.gif' => '30_063_school.svg',
			'titleicon/icon-hospital.gif' => '30_062_hospital.svg',
			'titleicon/icon-emergency.gif' => '30_062_hospital.svg',
			'titleicon/icon-note.gif' => '30_052_note.svg',
			'titleicon/icon-pencil.gif' => '30_053_pencil.svg',
			'titleicon/icon-magnifier.gif' => '30_055_search.svg',
			'titleicon/icon-clammyskin.gif' => '10_083_impatient.svg',
			'titleicon/icon-morning.gif' => '30_070_morning.svg',
			'titleicon/icon-day.gif' => '30_071_afternoon.svg',
			'titleicon/icon-evening.gif' => '30_073_evening.svg',
			'titleicon/icon-night.gif' => '30_073_evening.svg',
			'titleicon/icon-mail.gif' => '30_054_email.svg',
		];

		$titleIcon = Hash::get($map, [$titleIcon]);
		if ($titleIcon) {
			// @see https://github.com/NetCommons3/NetCommons/blob/3.1.0/View/Helper/TitleIconHelper.php#L211
			$titleIcon = '/net_commons/img/title_icon/' . $titleIcon;
		}

		return $titleIcon;
	}

/**
 * Convert nc2 timezone_offset.
 *
 * @param string $timezoneOffset Nc2 timezone_offset.
 * @return string converted nc3 timezone.
 */
	protected function _convertTimezone($timezoneOffset) {
		$map = [
			'-12.0' => 'Pacific/Kwajalein',
			'-11.0' => 'Pacific/Midway',
			'-10.0' => 'Pacific/Honolulu',
			'-9.0' => 'America/Anchorage',
			'-8.0' => 'America/Los_Angeles',
			'-7.0' => 'America/Denver',
			'-6.0' => 'America/Chicago',
			'-5.0' => 'America/New_York',
			'-4.0' => 'America/Dominica',
			'-3.5' => 'America/St_Johns',
			'-3.0' => 'America/Argentina/Buenos_Aires',
			'-2.0' => 'Atlantic/South_Georgia',
			'-1.0' => 'Atlantic/Azores',
			'0.0' => 'UTC',
			'1.0' => 'Europe/Brussels',
			'2.0' => 'Europe/Athens',
			'3.0' => 'Asia/Baghdad',
			'3.5' => 'Asia/Tehran',
			'4.0' => 'Asia/Muscat',
			'4.5' => 'Asia/Kabul',
			'5.0' => 'Asia/Karachi',
			'5.5' => 'Asia/Kolkata',
			'6.0' => 'Asia/Dhaka',
			'7.0' => 'Asia/Bangkok',
			'8.0' => 'Asia/Singapore',
			'9.0' => 'Asia/Tokyo',
			'9.5' => 'Australia/Darwin',
			'10.0' => 'Asia/Vladivostok',
			'11.0' => 'Australia/Sydney',
			'12.0' => 'Asia/Kamchatka'
		];

		return Hash::get($map, [$timezoneOffset], 'Asia/Tokyo');
	}

}
