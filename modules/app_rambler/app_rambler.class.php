<?php
class rambler_city extends module {
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
        $ifexist = SQLSelect('SELECT id FROM rambler_city');
	    if(empty($ifexist)) $out['NO_CITY'] = 1;

	    if($this->view_mode == 'addcity') {
		unset($out['NO_CITY']);
		$out['ADD_CITY_FORM'] == '1';
    	}
	
	    if($this->view_mode == 'citySearch' && !empty($this->id)) {
		$this->id = strip_tags($this->id);
		var_dump($this->id);
	    }
	
		
		$out['CYCLE_STATUS'] = getGlobal("ThisComputer.cycle_{$this->name}");
		
		$out['VERSION_MODULE'] = $this->version;
	}










	function DeleteLinkedProperties() {
		$properties = SQLSelect("SELECT * FROM rambler_city WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");

		if (!empty($properties)) {
			foreach ($properties as $prop) {
				removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
			}
		}
	}
	
	function DeleteCycleProperties() {
      $cycle_name = 'cycle_' . $this->name;
      $cycle_props = array("{$cycle_name}Run", "{$cycle_name}Control", "{$cycle_name}Disabled", "{$cycle_name}AutoRestart");

      $object = getObject('ThisComputer');

      foreach ($cycle_props as $property) {
         $property_id = $object->getPropertyByName($property, $object->class_id, $object->id);
         if ($property_id) {
            $value_id = getValueIdByName($object->object_title, $property);
            if ($value_id) {
               SQLExec("DELETE FROM phistory WHERE VALUE_ID={$value_id}");
               SQLExec("DELETE FROM pvalues WHERE ID={$value_id}");
            }
            if ($object->class_id != 0) {
               SQLExec("DELETE FROM properties WHERE ID={$property_id}");
            }
          }
      }

      SQLExec("DELETE FROM cached_values WHERE KEYWORD LIKE '%{$cycle_name}%'");
      SQLExec("DELETE FROM cached_ws WHERE PROPERTY LIKE '%{$cycle_name}%'");
   }
	
	function processCycle() {
		setGlobal("cycle_{$this->name}", '1');

		$this->getInfomation();
	}

	function install($data='') {
		//Укажем нормальную версию модуля, а то че как криво. Вообще нужно всем так делать.		
		//SQLExec("UPDATE `plugins` SET `CURRENT_VERSION` = '".dbSafe($this->version)."' WHERE `MODULE_NAME` = '".dbSafe($this->name)."';");
		
		parent::install();
	}
	
	function uninstall() {
		echo '<br>' . date('H:i:s') . " Uninstall module {$this->name}.<br>";

		// Остановим цикл модуля.
		echo date('H:i:s') . " Stopping cycle cycle_{$this->name}.php.<br>";
		setGlobal("cycle_{$this->name}", 'stop');
		// Нужна пауза, чтобы главный цикл обработал запрос.
		$i = 0;
		while ($i < 6) {
		echo '.';
		$i++; 
		sleep(1);
		}

		// Удалим слинкованные свойства объектов у метрик каждого ТВ.
		echo '<br>' . date('H:i:s') . ' Delete linked properties.<br>';
		$this->DeleteLinkedProperties();

		// Удаляем таблицы модуля из БД.
		echo date('H:i:s') . ' Delete DB tables.<br>';
		SQLExec('DROP TABLE IF EXISTS rambler_city');
		SQLExec('DROP TABLE IF EXISTS rambler_value');

		// Удаляем служебные свойства контроля состояния цикла у объекта ThisComputer.
		echo date('H:i:s') . ' Delete cycles properties.<br>';
		$this->DeleteCycleProperties();

		// Удаляем модуль с помощью "родительской" функции ядра.
		echo date('H:i:s') . ' Delete files and remove frome system.<br>';
		parent::uninstall();
	}
	
	function dbInstall($data = '') {
      $data = <<<EOD
        rambler_city: ID int(15) unsigned NOT NULL auto_increment
        rambler_city: TITLE varchar(255) NOT NULL DEFAULT ''
        rambler_city: ADD varchar(255) NOT NULL DEFAULT ''

	


		rambler_value: ID int(15) unsigned NOT NULL auto_increment
        rambler_value: CITY_ID varchar(100) NOT NULL DEFAULT ''
        rambler_value: TITLE varchar(255) NOT NULL DEFAULT ''
        rambler_value: VALUE varchar(255) NOT NULL DEFAULT ''
        rambler_value: UPDATE varchar(255) NOT NULL DEFAULT ''
        rambler_value: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
        rambler_value: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
        rambler_value: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''


        
EOD;
		parent::dbInstall($data);
   }
}