<?php
/**
 * Nc2ToNc3 view template
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */
?>

<?php echo $this->NetCommonsForm->create('Nc2ToNc3'); ?>
	<div class="panel panel-default">
		<div class="panel-heading">
			<?php echo __d('nc2_to_nc3', 'Input the database connection information of NetCommons2.'); ?>
		</div>

		<div class="panel-body">
			<?php
				echo $this->NetCommonsForm->input(
					'database',
					[
						'label' => __d('nc2_to_nc3', 'Database'),
						'required' => true,
					]
				);
				echo $this->NetCommonsForm->input('prefix', ['label' => __d('nc2_to_nc3', 'Prefix')]);
			?>

			<hr>

			<div class="row">
				<div class="col-xs-offset-1 col-xs-11">
					<?php
						echo $this->MessageFlash->description(
							__d('nc2_to_nc3', 'Change that below, if it is different from NetCommons3.')
						);
						/* tinyint(1)をbooleanにしないDboSourceを使用するようにしたため不要
						echo $this->NetCommonsForm->input(
							'datasource',
							[
								'type' => 'select',
								'label' => __d('nc2_to_nc3', 'Datasource'),
								'options' => [
									'Database/Mysql' => 'Mysql',
									//'Database/Postgres' => 'Postgresql'	// Installプラグインもコメントになっていたので合わせた
								]
							]
						);
						*/
						echo $this->NetCommonsForm->input('host', ['label' => __d('nc2_to_nc3', 'Host')]);
						echo $this->NetCommonsForm->input('port', ['label' => __d('nc2_to_nc3', 'Port')]);
						echo $this->NetCommonsForm->input('login', ['label' => __d('nc2_to_nc3', 'Login')]);
						echo $this->NetCommonsForm->input('password', ['label' => __d('nc2_to_nc3', 'Password')]);

						echo $this->Form->hidden('persistent');
						echo $this->Form->hidden('encoding');
						echo $this->Form->hidden('schema');	// PostgreSQL データベース利用時のみらしいがDATABASE_CONFIGにあったので残す
					?>
				</div>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading">
			<?php echo __d('nc2_to_nc3', 'Input the file path of NetCommons2.'); ?>
		</div>

		<div class="panel-body">
			<?php
				echo $this->NetCommonsForm->input('upload_path', ['label' => __d('nc2_to_nc3', 'Upload file path')]);

				// item.iniファイル定数取得処理未実装
				echo $this->Form->hidden('items_ini_path');
				/*
				echo $this->NetCommonsForm->input(
					'items_ini_path',
					[
						'label' => __d('nc2_to_nc3', 'items.ini file path'),
						'help' => __d('nc2_to_nc3', 'If you do not enter it, use the default value.'),
					]
				);
				*/
			?>
		</div>
	</div>

	<div class="panel-footer text-center">
		<?php echo $this->Button->save(__d('net_commons', 'OK')); ?>
	</div>
<?php echo $this->NetCommonsForm->end();
