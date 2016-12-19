<?php
/**
 * Nc2ToNc3 Shell
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ModelManager', 'Nc2ToNc3.Utility');
App::uses('Nc2ToNc3Controller', 'Nc2ToNc3.Controller');

/**
 * Nc2ToNc3 Shell
 *
 */
class Nc2ToNc3Shell extends AppShell {

	//public $uses = array('Nc2ToNc3.Config');

/**
 * Main
 *
 * @return void
 */
	public function main() {
		// Flashメッセージを利用するためコントローラーを使用→不要かも
		$Nc2ToNc3Controller = new Nc2ToNc3Controller();
		$Nc2ToNc3Controller->constructClasses();

		$config['database'] = $this->params['database'];
		$config['prefix'] = $this->params['prefix'];
		if (!Nc2ModelManager::migration($Nc2ToNc3Controller, $config)) {
			return $this->error(CakeSession::read('Message.' . Nc2ModelManager::MESSAGE_KEY));
		}

		var_dump(99);
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
		);

		return $parser;
	}
}