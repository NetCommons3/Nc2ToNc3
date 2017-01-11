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

/**
 * Nc2ToNc3 Shell
 *
 */
class Nc2ToNc3Shell extends AppShell {

/**
 * Main
 *
 * @return void
 */
	public function main() {
		$request = new CakeRequest();
		$request->data['Nc2ToNc3'] = $this->params;
		$Nc2ToNc3Controller = new Nc2ToNc3Controller($request);

		// TODOーログイン処理

		$Nc2ToNc3Controller->constructClasses();
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$Nc2ToNc3Controller->migration();

		$message = CakeSession::read('Message.' . Nc2ToNc3::MESSAGE_KEY);
		if ($message) {
			return $this->error($message);
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
		);

		return $parser;
	}
}