<?php
/**
 * Nc2ToNc3Shell::getOptionParser()のテスト
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Mitsuru Mutaguchi <mutaguchi@opensource-workshop.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('Nc2ToNc3ConsoleTestCase', 'Nc2ToNc3.TestSuite');

/**
 * Nc2ToNc3Shell::getOptionParser()のテスト
 *
 * @author Mitsuru Mutaguchi <mutaguchi@opensource-workshop.jp>
 * @package NetCommons\Nc2ToNc3\Test\Case\Console\Command\Nc2ToNc3Shell
 */
class Nc2ToNc3ConsoleCommandNc2ToNc3ShellGetOptionParserTest extends Nc2ToNc3ConsoleTestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array();

/**
 * Plugin name
 *
 * @var string
 */
	public $plugin = 'nc2_to_nc3';

/**
 * Shell name
 *
 * @var string
 */
	protected $_shellName = 'Nc2ToNc3Shell';

/**
 * getOptionParser()のテスト
 *
 * @return void
 */
	public function testGetOptionParser() {
		$shell = $this->_shellName;
		$this->$shell = $this->loadShell($shell);

		//事前準備
		////TODO: サブタスクがあれば、Mock作る
		//$task = 'TODO:サブタスク名(キャメル形式)';
		//$this->$shell->$task = $this->getMock($task,
		//		array('getOptionParser'), array(), '', false);
		//$this->$shell->$task->expects($this->once())->method('getOptionParser')
		//	->will($this->returnValue(true));

		//テスト実施
		$result = $this->$shell->getOptionParser();

		//チェック
		$this->assertEquals('ConsoleOptionParser', get_class($result));

		////サブタスクヘルプのチェック
		//$expected = array();
		//$actual = array();
		//$subCommands = array(
		//	'TODO:サブタスク名(snake形式)' => 'TODO:サブタスクのヘルプ',
		//);
		//foreach ($subCommands as $subCommand => $helpMessage) {
		//	$expected[] = $subCommand . ' ' . $helpMessage;
		//	$actual[] = $result->subcommands()[$subCommand]->help(strlen($subCommand) + 1);
		//}
		//$this->assertEquals($expected, $actual);

		////オプションヘルプのテスト
		//$expected = array();
		//$actual = array();
		//$paserOptions = array(
		//	'TODO:オプションキー' => 'TODO:オプションヘルプ',
		//);
		//foreach ($paserOptions as $option => $helpMessage) {
		//	$expected[] = '--' . $option . ' ' . $helpMessage;
		//	$actual[] = $result->options()[$option]->help(strlen($option) + 3);
		//}
		//$this->assertEquals($expected, $actual);

		////引数ヘルプのチェック
		//$expected = array();
		//$actual = array();
		//$arguments = array(
		//	'TODO:引数名' => 'TODO:引数ヘルプ'
		//);
		//$index = 0;
		//foreach ($arguments as $arg => $helpMessage) {
		//	$expected[] = $arg . ' ' . $helpMessage;
		//	$actual[] = $result->arguments()[$index]->help(strlen($arg) + 1);
		//}
		//$this->assertEquals($expected, $actual);
	}

}
