<?php
class app_rambler extends module {
	function __construct() {
		$this->name="app_rambler";
		$this->title="Модуль Рамблер";
		$this->module_category="<#LANG_SECTION_APPLICATIONS#>";
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
		global $ajax;
		
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
		if (isset($ajax)) {
			$this->ajax=$ajax;
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
		if(empty($this->view_mode)) {
			//Выгружаем список добавленых городов и отдаем в шаблон
			$out['CITY_ALL'] = SQLSelect('SELECT * FROM rambler_weather_city');
			foreach($out['CITY_ALL'] as $key => $value) {
				$data = SQLSelect("SELECT * FROM rambler_weather_value WHERE CITY_ID = '".$value['ID']."' AND (TITLE = 'current_weather.icon' OR TITLE = 'current_weather.temperature')");
				foreach($data as $cityValueKey => $cityValueData) {
					if($cityValueData['TITLE'] == 'current_weather.icon') $out['CITY_ALL'][$key]['CURRENT_WEATHER_ICON'] = $cityValueData['VALUE'];
					if($cityValueData['TITLE'] == 'current_weather.temperature') {
						$out['CITY_ALL'][$key]['CURRENT_WEATHER_TEMPERATURE'] = $cityValueData['VALUE'];
						$out['CITY_ALL'][$key]['UPDATE'] = date('d.m.Y H:i', $cityValueData['UPDATE']);
					}
				}
			}
		}

	    if($this->view_mode == 'citysearch' && !empty($this->id)) {
			//Действия после поиска и передачи города
			$data = $this->callAPI('https://weather.rambler.ru/api/v3/map_towns/?url_path='.$this->id);
			$data = json_decode($data, TRUE);
			
			$ifExist = SQLSelectOne("SELECT ID FROM rambler_weather_city WHERE URL_PATH = '".DBSafe($data['current_town']["url_path"])."'");
			
			if(!$ifExist) {
				$rec['TITLE'] = $data['current_town']["name"];
				$rec['URL_PATH'] = $data['current_town']["url_path"];
				$rec['GEO_CODE'] = $data['current_town']["geo_location"]["lat"].', '.$data['current_town']["geo_location"]["lng"];
				$rec['ADD'] = time();
				
				SQLInsert('rambler_weather_city', $rec);
				
				$this->loadWeatherNow($rec['URL_PATH']);
			}
			$this->redirect('?');
	    }
	
		if($this->view_mode == 'loaddata' && !empty($this->id)) {
			//Действия при входе в город, тут выгружаем все значения
			$out['CITY_INFO'] = SQLSelect('SELECT * FROM rambler_weather_city WHERE id = '.DBSafe($this->id));
			$arrayInDB = SQLSelect('SELECT * FROM rambler_weather_value WHERE city_id = '.DBSafe($this->id));
			$arrayReady = [];
			$arrayOut = [];
			
			foreach($arrayInDB as $key => $value) {
				$searchType = explode('.', $value["TITLE"]);
				$arrayInDB[$key]["TITLE"] = $searchType[1];
				$arrayInDB[$key]["CONTENT_TYPE"] = mb_strtoupper($searchType[0]);
				$arrayInDB[$key]["UPDATE_HUMAN"] = date('d.m.Y H:i', $value["UPDATE"]);
				if(in_array($searchType[0], $arrayReady) == false) $arrayReady[] = mb_strtoupper($searchType[0]);
				
			}
			
			foreach($arrayReady as $value) {
				$arrayOut[$value] = $arrayInDB;
			}
			
			$out['CITY_DATA'] = $arrayOut;
	    }
		
		if($this->view_mode == 'savelink' && !empty($this->id)) {
			//Действия при связке со свойствами, меняем в БД валуе и привязываем, далее редирект обратно
			
	    }
		
		if($this->view_mode == 'loadweather' && !empty($this->id)) {
			//Действия при ручном обновлении
			$this->loadWeatherNow($this->id);
			$this->redirect('?');
	    }
		
		
		

		$out['VERSION_MODULE'] = $this->version;
	}
	
	function loadWeatherNow($url_path = '') {
		if($url_path != '') {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city WHERE URL_PATH = '".DBSafe($url_path)."'");
		} else {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city");
		}
		
		foreach($getAllCity as $key => $value) {
			$data = $this->callAPI('https://weather.rambler.ru/api/v3/now/?only_current=1&url_path='.$value['URL_PATH']);
			$data = json_decode($data, TRUE);
			
			foreach($data["current_weather"] as $weatherNowKey => $weatherNowValue) {
				if(!is_array($weatherNowValue)) {
					$rec['TITLE'] = 'current_weather.'.$weatherNowKey;
					$rec['VALUE'] = $weatherNowValue;
					$rec['CITY_ID'] = $value['ID'];
					$rec['UPDATE'] = time();
					
					if($weatherNowKey == 'temperature' && $weatherNowValue > 0) {
						$rec['VALUE'] = '+'.$weatherNowValue;
					}
					
					$ifExist = SQLSelectOne("SELECT ID FROM rambler_weather_value WHERE TITLE = '".$rec['TITLE']."' AND CITY_ID = '".$rec['CITY_ID']."'");
					if(!$ifExist) {
						SQLInsert('rambler_weather_value', $rec);
					} else {
						debmes('UPDATE');
						$rec['ID'] = $ifExist['ID'];
						SQLUpdate('rambler_weather_value', $rec);
					}
				}
			} 
		}
	}
	
	function usual(&$out) {
		//Обработка AJAX 
		global $request;
		
		if($this->ajax == 1 && $request == 'whereiam') {
			echo $this->whereiam();
			die();
    	}
	}
	
	function callAPI($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		$html = curl_exec($ch);
		curl_close($ch);
		 
		return $html;
	}

	function whereiam() {
		$data = $this->callAPI('https://weather.rambler.ru/location/current');
		//$data = json_decode($data, TRUE);
		return $data;
	}

	function DeleteLinkedProperties() {
		$properties = SQLSelect("SELECT * FROM rambler_weather_value WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");

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
        rambler_weather_city: URL_PATH varchar(255) NOT NULL DEFAULT ''
        rambler_weather_city: GEO_CODE varchar(255) NOT NULL DEFAULT ''
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
