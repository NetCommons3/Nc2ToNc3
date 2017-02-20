<?php
/**
 * Nc2ToNc3 Shell
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3Controller', 'Nc2ToNc3.Controller');
App::uses('Auth', 'Controller/Component');
App::uses('ComponentCollection', 'Controller');

/**
 * Nc2ToNc3 Shell
 *
 */
class Nc2ToNc3Shell extends AppShell {

/**
 * use model
 *
 * @var array
 */
	public $uses = ['Users.User'];

/**
 * Main
 *
 * @return void
 */
	public function main() {
		$Nc2ToNc3Controller = new Nc2ToNc3Controller();
		$Nc2ToNc3Controller->constructClasses();

		// TODOーログイン処理
		// とりあえず強制的に管理者
		$user = $this->User->findById('1', null, null, -1);
		$Nc2ToNc3Controller->Auth->login($user['User']);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		// CakeObject::requestActionを使用すると、AuthComponent::_isAllowedでredirectされる
		// $Nc2ToNc3Controller::migrationを呼び出した方が良いのか？
		// Model呼び出し(Nc2ToNc3::migration)の方が良いのか？
		$request = $this->requestAction(
			'nc2_to_nc3/nc2_to_nc3/migration/',
			[
				'data' => [
					'Nc2ToNc3' => $this->params
				]
			]
		);

		// Errorの判断が違う気がする
		if (!$request) {
			$this->out('Error!!');
			return;
		}

		$this->out('Success!!');
	}

/**
 * Gets the option parser instance and configures it.
 *
 * By overriding this method you can configure the ConsoleOptionParser before returning it.
 *
 * @return ConsoleOptionParser
 * @link http://book.cakephp.org/2.0/en/console-and-shells.html#Shell::getOptionParser
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addOption(
			'database',
			[
				'help' => 'database name of nc2',
				'short' => 'd'
			]
		)->addOption(
			'prefix',
			[
				'help' => 'table prefix name of nc2',
				'short' => 'p'
			]
		)->addOption(
			'upload_path',
			[
				'help' => 'upload path of nc2',
				'short' => 'u'
			]
		);

		return $parser;
	}
}