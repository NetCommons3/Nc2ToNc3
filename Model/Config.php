<?php

App::uses('AppModel', 'Model');

class Config extends AppModel {
	public $useTable = 'config';
	public $useDbConfig = 'nc2';

	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $this->useTable, $this->useDbConfig);
	}
}
