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
		$config['database'] = $this->params['database'];
		$config['prefix'] = $this->params['prefix'];
		Nc2ModelManager::createNc2Connection($config);

		if (!Nc2ModelManager::validateNc2Connection()) {
			return $this->error(__d('nc2_to_nc3', 'Nc2 version must be %s', Nc2ModelManager::VALID_VERSION));
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