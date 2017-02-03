<?php
/**
 * Nc2ToNc3Plugin
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3Plugin
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 *
 */
class Nc2ToNc3Plugin extends Nc2ToNc3AppModel {

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
 * Get id map
 *
 * @return array|string Id map.
 */
	public function getIdMap() {
		$actionNameToKeyMap = [
			//'authority_view_admin_init' => 'user_roles',
			//'cleanup_view_main_init' => null,
			//'backup_view_main_init' => null,
			//'holiday_view_admin_init' => 'holidays',
			//'mobile_view_admin_init' => null,
			//'module_view_admin_init' => 'plugin_manager',
			//'policy_view_admin_init' => 'user_roles',
			//'room_view_admin_init' => 'rooms',
			//'security_view_main_security' => 'system_manager',
			//'share_view_admin_init' => null,
			//'system_view_main_general' => 'system_manager',
			//'system_view_main_general' => 'site_manager',
			//'user_view_main_search' => 'user_attributes',
			//'user_view_main_search' => 'user_manager',
			//'userinf_view_main_init' => 'users',
			'cabinet_view_main_init' => 'cabinets',
			'announcement_view_main_init' => 'announcements',
			//'chat_view_main_init' => null
			'bbs_view_main_init' => 'bbses',
			'calendar_view_main_init' => 'calendars',
			//'assignment_view_main_init' => null,
			'counter_view_main_init' => 'access_counters',
			'circular_view_main_init' => 'circular_notices',
			'iframe_view_main_init' => 'iframes',
			//'imagine_view_main_init' => null,
			//'language_view_main_init' => null,
			'journal_view_main_init' => 'blogs',
			'login_view_main_init' => 'auth',
			'linklist_view_main_init' => 'links',
			'menu_view_main_init' => 'menus',
			//'multidatabase_view_main_init' => null,
			//'online_view_main_init' => null,
			'photoalbum_view_main_init' => 'photo_albums',
			'questionnaire_view_main_init' => 'questionnaires',
			//'pm_view_main_init' => null,
			'registration_view_main_init' => 'registrations',
			'quiz_view_main_init' => 'quizzes',
			'rss_view_main_init' => 'rss_readers',
			//'reservation_view_main_init' => null,
			'search_view_main_init' => 'searches',
			'todo_view_main_init' => 'tasks',
			'whatsnew_view_main_init' => 'topics',
			'multimedia_view_main_init' => 'videos',
			'faq_view_main_init' => 'faqs',
		];

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$map = $Nc2ToNc3Map->getMap('Plugin');
		if ($map &&
			count($map) == count($actionNameToKeyMap)
		) {
			return $map;
		}

		/* @var $Nc2PagesModulesLink AppModel */
		$Nc2Module = $this->getNc2Model('modules');
		$query = [
			'fields' => [
				'Nc2Module.module_id',
				'Nc2Module.action_name'
			],
			'conditions' => [
				'Nc2Module.system_flag' => '0'
			],
			'recursive' => -1
		];
		$nc2Modules = $Nc2Module->find('all', $query);

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$map = [];
		foreach ($nc2Modules as $nc2Module) {
			$nc2ActionName = $nc2Module['Nc2Module']['action_name'];
			if (!isset($actionNameToKeyMap[$nc2ActionName])) {
				continue;
			}

			$mapForSave = [];
			$nc2ModuleId = $nc2Module['Nc2Module']['module_id'];

			$map[$nc2ModuleId] = [
				'Plugin' => [
					'key' => $actionNameToKeyMap[$nc2ActionName]
				]
			];

			$mapForSave[$nc2ModuleId] = $map[$nc2ModuleId];
			$Nc2ToNc3Map->saveMap('Plugin', $mapForSave);
		}

		return $map;
	}

}
