<?php
class app_rambler extends module {
	function __construct() {
		$this->name="app_rambler";
		$this->title="Модуль Рамблер";
		$this->module_category="<#LANG_SECTION_DEVICES#>";
		$this->version="1.0 beta";
		$this->checkInstalled();
	}

	function saveParams($data=1) {
		$p=array();
		if (IsSet($this->id)) {
			$p["id"]=$this->id;
		}
		if (IsSet($this->view_mode)) {
			$p["view_mode"]=$this->view_mode;
		}
		if (IsSet($this->edit_mode)) {
			$p["edit_mode"]=$this->edit_mode;
		}
		if (IsSet($this->tab)) {
			$p["tab"]=$this->tab;
		}
		
		return parent::saveParams($p);
	}

	function getParams() {
		global $id;
		global $mode;
		global $view_mode;
		global $edit_mode;
		global $tab;
		
		if (isset($id)) {
			$this->id=$id;
		}
		if (isset($mode)) {
			$this->mode=$mode;
		}
		if (isset($view_mode)) {
			$this->view_mode=$view_mode;
		}
		if (isset($edit_mode)) {
			$this->edit_mode=$edit_mode;
		}
		if (isset($tab)) {
			$this->tab=$tab;
		}
	}

	function run() {
		global $session;
		$out=array();
		
		if ($this->action=='admin') {
			$this->admin($out);
		} else {
			$this->usual($out);
		}
		if (IsSet($this->owner->action)) {
			$out['PARENT_ACTION']=$this->owner->action;
		}
		if (IsSet($this->owner->name)) {
			$out['PARENT_NAME']=$this->owner->name;
		}
		$out['VIEW_MODE']=$this->view_mode;
		$out['EDIT_MODE']=$this->edit_mode;
		$out['ID']=$this->id;
		$out['MODE']=$this->mode;
		$out['ACTION']=$this->action;
		$this->data=$out;
		$p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
		$this->result=$p->result;
	}
	
	
   

    function admin(&$out) {
		//Выгружаем список добавленых городов и отдаем в шаблон
	    $out['CITY_ALL'] = SQLSelect('SELECT * FROM rambler_weather_city');

	    if($this->view_mode == 'addcity') {
			//Действия после нажатия кнопки ДОБАВИТЬ ГОРОД
			//Сдесь будет другой шаблон
    	}
	
	    if($this->view_mode == 'citysearch' && !empty($this->id)) {
			//Действия после поиска и передачи города
	    }
	
		if($this->view_mode == 'cityshow' && !empty($this->id)) {
			//Действия при входе в город, тут выгружаем все значения
			$out['CITY_DATA'] = SQLSelect('SELECT * FROM rambler_weather_value WHERE city_id = '.DBSafe($this->id));
	    }
		
		if($this->view_mode == 'savelink' && !empty($this->id)) {
			//Действия при связке со свойствами, меняем в БД валуе и привязываем, далее редирект обратно
			
	    }
		
		
		

		$out['VERSION_MODULE'] = $this->version;
	}


	function DeleteLinkedProperties() {
		$properties = SQLSelect("SELECT * FROM rambler_value WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");

		if (!empty($properties)) {
			foreach ($properties as $prop) {
				removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
			}
		}
	}
	
	function install($data='') {	
		parent::install();
	}
	
	function uninstall() {
		$this->DeleteLinkedProperties();

		// Удаляем таблицы модуля из БД.
		echo date('H:i:s') . ' Delete DB tables.<br>';
		SQLExec('DROP TABLE IF EXISTS rambler_weather_city');
		SQLExec('DROP TABLE IF EXISTS rambler_weather_value');
		parent::uninstall();
	}
	
	function dbInstall($data = '') {
      $data = <<<EOD
        rambler_weather_city: ID int(15) unsigned NOT NULL auto_increment
        rambler_weather_city: TITLE varchar(255) NOT NULL DEFAULT ''
        rambler_weather_city: ADD varchar(255) NOT NULL DEFAULT ''


		rambler_weather_value: ID int(15) unsigned NOT NULL auto_increment
        rambler_weather_value: CITY_ID varchar(100) NOT NULL DEFAULT ''
        rambler_weather_value: TITLE varchar(255) NOT NULL DEFAULT ''
        rambler_weather_value: VALUE varchar(255) NOT NULL DEFAULT ''
        rambler_weather_value: UPDATE varchar(255) NOT NULL DEFAULT ''
        rambler_weather_value: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
        rambler_weather_value: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
        rambler_weather_value: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
EOD;
		parent::dbInstall($data);
   }
}