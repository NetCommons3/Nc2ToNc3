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
 * 事前準備
 *
 * @return void
 */
	protected function _prepare() {
		if (! array_key_exists('database', $this->params)) {
			$this->params['database'] = $this->in(
				__d('nc2_to_nc3', 'Enter database name of nc2?')
			);
		}
		if (! array_key_exists('prefix', $this->params)) {
			$this->params['prefix'] = $this->in(
				__d('nc2_to_nc3', 'Enter table prefix name of nc2?')
			);
		}
		if (substr($this->params['prefix'], -1, 1) !== '_') {
			$this->params['prefix'] .= '_';
		}
		//if (! array_key_exists('host', $this->params)) {
		//	$this->params['host'] = $this->in(
		//		__d('nc2_to_nc3', 'Enter database host name of nc2?')
		//	);
		//}
		//if (! array_key_exists('port', $this->params)) {
		//	$this->params['port'] = $this->in(
		//		__d('nc2_to_nc3', 'Enter database port of nc2?')
		//	);
		//}
		//if (! array_key_exists('login', $this->params)) {
		//	$this->params['login'] = $this->in(
		//		__d('nc2_to_nc3', 'Enter database login user of nc2?')
		//	);
		//}
		//if (! array_key_exists('password', $this->params)) {
		//	$this->params['password'] = $this->in(
		//		__d('nc2_to_nc3', 'Enter database login password of nc2?')
		//	);
		//}
		if (! array_key_exists('upload_path', $this->params)) {
			$this->params['upload_path'] = $this->in(
				__d('nc2_to_nc3', 'Enter upload path of nc2?')
			);
		}
		if (substr($this->params['upload_path'], -1, 1) !== '/') {
			$this->params['upload_path'] .= '/';
		}
		if (! array_key_exists('base_url', $this->params)) {
			$this->params['base_url'] = $this->in(
				__d(
					'nc2_to_nc3',
					'Enter url of nc2 for converting link in WYSIWYG content?(ex.http://example.com/nc2)'
				)
			);
		}
		if (! array_key_exists('nc3base', $this->params)) {
			$this->params['nc3base'] = $this->in(
				__d('nc2_to_nc3', 'Enter sub directory name?(ex."/dirname1/dirname2") If root is top, enter "/".')
			);
		}

		if (! array_key_exists('exclude', $this->params)) {
			$this->params['exclude'] = '';
		}
	}

/**
 * Main
 *
 * @return void
 */
	public function main() {
		$this->_prepare();

		// Router::url('/') で取得する値が、cakeコマンドのパスになってしまうので、オプションにした。
		// 定数ROOT,WWW_ROOT,APP_DIR,WEBROOT_DIR を駆使すればいけそうな気がしたが、VirtualHostの設定があった場合は無理。
		// CakePHPのDocumentにも「ドメインを手作業で設定する必要があります。」とある。
		// @see https://book.cakephp.org/2.0/ja/console-and-shells.html#cli
		//
		// 問題発生個所
		// Nc2ToNc3WysiwygBehavior::__getStrReplaceArgumentsOfTitleIcon:WysiwygのTitleIconのURL取得処理
		//
		// 何か情報あれば対応する。
		//
		// 以下、参考にしたソースコードのURL
		// @see https://github.com/NetCommons3/NetCommons3/blob/3.1.0/app/Console/cake#L37-L40
		// @see https://github.com/cakephp/cakephp/blob/2.9.8/lib/Cake/Console/ShellDispatcher.php#L283-L322
		// @see https://github.com/cakephp/cakephp/blob/2.9.8/lib/Cake/Console/ShellDispatcher.php#L122-L138
		// @see https://github.com/cakephp/cakephp/blob/2.9.8/lib/Cake/Network/CakeRequest.php#L307-L328
		if (!isset($this->params['nc3base'])) {
			$this->out('--nc3base option is required.Example "/dirname1/dirname2".If root is top, enter "/".');
			return;
		}

		$Nc2ToNc3Controller = new Nc2ToNc3Controller();
		$Nc2ToNc3Controller->constructClasses();

		// TODOーログイン処理
		// とりあえず強制的に管理者
		$user = $this->User->findById('1', null, null, -1);
		$Nc2ToNc3Controller->Auth->login($user['User']);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		Configure::write('App.base', $this->params['nc3base']);

		// Javascript等のHTMLタグを許可する
		Current::write('Permission.html_not_limited.value', 1);

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
			'nc3base',
			[
				'help' => 'sub directory name.Example "/dirname1/dirname2".If root is top, enter "/".',
			]
		)->addOption(
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
		)->addOption(
			'base_url',
			[
				'help' => 'url of nc2 for converting link in WYSIWYG content.(ex.http://example.com/nc2)',
				'short' => 'b'
			]
		)->addOption(
			'host',
			[
				'help' => 'host name of nc2',
			]
		)->addOption(
			'port',
			[
				'help' => 'database port of nc2',
			]
		)->addOption(
			'login',
			[
				'help' => 'database login user of nc2',
			]
		)->addOption(
			'password',
			[
				'help' => 'database login password of nc2',
			]
		)->addOption(
			'exclude',
			[
				'help' => 'migration exclude plugins. (ex. Faq,Video)',
			]
		);

		return $parser;
	}
}
