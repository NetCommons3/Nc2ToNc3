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
 * use model
 *
 * @var array
 */
	public $uses = ['Nc2ToNc3.Nc2ToNc3'];

/**
 * Main
 *
 * @return void
 */
	public function main() {
		// TODOーログイン処理

		$data['Nc2ToNc3'] = $this->params;
		if (!$this->Nc2ToNc3->migration($data)) {
			$this->error($this->Nc2ToNc3->getMigrationMessages());
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
		);

		return $parser;
	}
}