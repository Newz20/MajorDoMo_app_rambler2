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
				//$rec['GEO_CODE'] = $data['current_town']["geo_location"]["lat"].', '.$data['current_town']["geo_location"]["lng"];
				$rec['ADD'] = time();
				
				SQLInsert('rambler_weather_city', $rec);
				
				$this->loadWeatherNow($rec['URL_PATH']);
			}
			$this->redirect('?');
	    }
	
		if($this->view_mode == 'loaddata' && !empty($this->id)) {
			if(empty($this->mode)) $this->mode = 'current_weather';
			//Действия при входе в город, тут выгружаем все значения
			$city_info = SQLSelectOne('SELECT * FROM rambler_weather_city WHERE id = '.DBSafe($this->id).' ORDER BY ID');
			$out['CITY_TITLE'] = $city_info['TITLE'];
			$out['CITY_URL_PATH'] = $city_info['URL_PATH'];
			$arrayInDB = SQLSelect('SELECT * FROM rambler_weather_value WHERE CITY_ID = '.DBSafe($this->id).' ORDER BY ID');
			$arrayReady = [];
			$arrayOut = [];
			
			foreach($arrayInDB as $key => $value) {
				$searchType = explode('.', $value["TITLE"]);
				
				if(mb_strtoupper($this->mode) != mb_strtoupper($searchType[0])) {
					unset($arrayInDB[$key]);
					continue;
				}
				
				$arrayInDB[$key]["TITLE"] = $searchType[1];
				$arrayInDB[$key]["CONTENT_TYPE"] = mb_strtoupper($searchType[0]);
				$arrayInDB[$key]["UPDATE_HUMAN"] = date('d.m.Y H:i', $value["UPDATE"]);
				
				$arrayReady[] = mb_strtoupper($searchType[0]);
			}
			
			$arrayReady = array_unique($arrayReady);
			$arrayReady = array_values($arrayReady);
			
			foreach($arrayInDB as $key => $value) {
				$arrayOut['DATA'][] = $arrayInDB[$key];
			}
			
			$out['CITY_DATA'] = $arrayOut;
	    }
		
		if($this->view_mode == 'savelink') {
			//Действия при связке со свойствами, меняем в БД валуе и привязываем, далее редирект обратно
			global $id;
			global $mode;
			
			if(!$id || !$mode) $this->redirect('?');
			
			$skills = SQLSelect("SELECT * FROM `rambler_weather_value` WHERE CITY_ID = '" . DBSafe($id) . "' AND TITLE LIKE '".$mode.".%' ORDER BY ID");
			
			$total = count($skills);

			for ($i = 0; $i < $total; $i++) {
				$old_linked_object = $skills[$i]['LINKED_OBJECT'];
                $old_linked_property = $skills[$i]['LINKED_PROPERTY'];
                $old_linked_method = $skills[$i]['LINKED_METHOD'];
				
				global ${'linked_object' . $skills[$i]['ID']};
                $skills[$i]['LINKED_OBJECT'] = trim(${'linked_object' . $skills[$i]['ID']});
                global ${'linked_property' . $skills[$i]['ID']};
                $skills[$i]['LINKED_PROPERTY'] = trim(${'linked_property' . $skills[$i]['ID']});
                global ${'linked_method' . $skills[$i]['ID']};
                $skills[$i]['LINKED_METHOD'] = trim(${'linked_method' . $skills[$i]['ID']});

				SQLUpdate('rambler_weather_value', $skills[$i]);
				
				if ($old_linked_object != $skills[$i]['LINKED_OBJECT'] || $old_linked_property != $skills[$i]['LINKED_PROPERTY']) {
                    removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
                }
                if ($skills[$i]['LINKED_OBJECT'] && $skills[$i]['LINKED_PROPERTY']) {
                    addLinkedProperty($skills[$i]['LINKED_OBJECT'], $skills[$i]['LINKED_PROPERTY'], $this->name);
					//Запишем сразу значение
					if($skills[$i]['VALUE'] != gg($skills[$i]['LINKED_OBJECT'].'.'.$skills[$i]['LINKED_PROPERTY'])) {
						sg($skills[$i]['LINKED_OBJECT'].'.'.$skills[$i]['LINKED_PROPERTY'], $skills[$i]['VALUE']);
					}
                }
			}
			
			$this->redirect('?view_mode=loaddata&id='.$id.'&mode='.$mode);
	    }
		
		if($this->view_mode == 'loadweather' && !empty($this->id)) {
			//Действия при ручном обновлении
			
			$this->loadWeatherNow($this->id);
			//echo '<pre>';
			//var_dump($this->loadWeatherNow($this->id));
			//die();
			$this->redirect('?');
	    }
		
		if($this->view_mode == 'deletecity' && !empty($this->id)) {
			//Действия при ручном обновлении
			$this->DeleteLinkedProperties($this->id);
			
			SQLExec("DELETE FROM rambler_weather_value WHERE CITY_ID = '".DBSafe($this->id)."'");
			SQLExec("DELETE FROM rambler_weather_city WHERE ID = '".DBSafe($this->id)."'");
			
			$this->redirect('?');
	    }
		
		if($this->view_mode == 'autolink' && !empty($this->id) && !empty($this->mode) && !empty($this->tab)) {
			$this->autoLinkProp($this->mode, $this->id, $this->tab);
			$this->redirect('?view_mode=loaddata&id='.$this->id);
	    }

		$out['VERSION_MODULE'] = $this->version;
	}
	
	function moonPhaseText($deg) {
		$moon=array(
			"1" => "Новолуние",
			"2" => "Молодая луна",
			"3" => "Правая четверть",
			"4" => "Прибывающая луна",
			"5" => "Полнолуние",
			"6" => "Убывающая луна",
			"7" => "Последняя четверть",
			"8" => "Старая луна",
		);
		$i = ceil($deg/45);
		if($i == 0) $i=1;
		return $moon[$i];
	}
	
	function getWindDirectionText($text){
		$wind=array(
		  "N" => "Северный",
		  "NE" => "Северо-восточный",
		  "E" => "Восточный",
		  "SE" => "Юго-восточный",
		  "S" => "Южный",
		  "SW" => "Юго-западный",
		  "W" => "Западный",
		  "NW" => "Северо-западный",
		  "C" => "Штиль",
		);
	
		return $wind[$text];
	  }
		
	function magneticText($num) {
		if($num == "") $num = 10;
		$magnetic = array(
			"0" => "Спокойное магнитное поле",
			"1" => "Неустойчивое магнитное поле",
			"2" => "Слабо возмущённое магнитное поле",
			"3" => "Возмущённое магнитное поле",
			"4" => "Магнитная буря",
			"5" => "Большая магнитная буря",
			"10" => "Информация отсутствует",
		);

		return $magnetic[$num];
	}
	
	function uvText($num){
		$uv=array(
			"0" => "Низкий",
			"1" => "Низкий",
			"2" => "Низкий",
			"3" => "Средний",
			"4" => "Средний",
			"5" => "Средний",
			"6" => "Высокий",
			"7" => "Высокий",
			"8" => "Очень высокий",
			"9" => "Очень высокий",
			"10" => "Экстремальный",
			"11" => "Экстремальный",
		);
		
		if(empty($uv[$num])) $num = 11;

		return $uv[$num];
	}
	
	function iconText($text){
		$icon=array(
			"clear" => "Ясно",
			"clear-night" => "Ясно",
			"cloudy" => "Облачно",
			"partly-cloudy" => "Переменная облачность",
			"partly-cloudy-night" => "Переменная облачность",
			"fog" => "Туман",
			"light-rain" => "Слабый дождь",
			"occ-rain" => "Временами дождь",
			"light-rain-night" => "Временами дождь",
			"occ-snow" => "Временами снег",
			"light-snow-night" => "Временами снег",
			"rain" => "Дождь",
			"rain-night" => "Дождь",
			"snow" => "Снег",
			"snow-night" => "Снег",
			"sleet" => "Снег с дождем",
			"thunder" => "Гроза",
		);

		return $icon[$text];
	}
	
	function autoLinkProp($url_path, $city_id, $type) {
		//$url_path_obj = substr($url_path, 2);
		$url_path_obj = str_replace('-','_',$url_path);
		addClass('app_rambler');
		addClassObject('app_rambler', $url_path_obj);
		
		$loadAllProp = SQLSelect("SELECT * FROM rambler_weather_value WHERE CITY_ID = '".$city_id."' AND TITLE LIKE '".$type.".%' AND LINKED_OBJECT = '' AND LINKED_PROPERTY = ''");
		
		$obj = getObject($url_path_obj);

		foreach($loadAllProp as $key => $value) {
			if(empty($value['TITLE'])) continue;
			
			$exValue = explode('.', $value['TITLE']);
			$obj->setProperty($exValue[1], $value['VALUE']);
			
			addLinkedProperty($url_path_obj, $exValue[1], $this->name);
			
			SQLExec("UPDATE rambler_weather_value SET LINKED_OBJECT = '".$url_path_obj."', LINKED_PROPERTY = '".$exValue[1]."' WHERE CITY_ID = '".$city_id."' AND TITLE = '".$value['TITLE']."'");		
		
		}
	}
	
	function serverIP($id, $cycleupdate = 0) {
		$data = $this->callAPI('https://kraken.rambler.ru/userip');
		$data = trim($data);
		
		$rec['TITLE'] = 'userip.userip';
		$rec['VALUE'] = $data;
		$rec['CITY_ID'] = $id;
		$rec['UPDATE'] = time();
		
		$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$rec['TITLE']."' AND CITY_ID = '".$rec['CITY_ID']."'");
		
		if(!$ifExist) {
			SQLInsert('rambler_weather_value', $rec);
		} else {
			//Обновляем свойства
			$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $rec['VALUE'], $ifExist['VALUE'], $id);
			
			//if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) return;
			
			$rec['ID'] = $ifExist['ID'];
			SQLUpdate('rambler_weather_value', $rec);
		}
	}
	
	function loadCurrenciesNow($url_path = '', $cycleupdate = 0) {
		if($url_path != '') {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city WHERE URL_PATH = '".DBSafe($url_path)."'");
		} else {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city");
		}
		
		foreach($getAllCity as $key => $value) {
			$data = $this->callAPI('https://www.rambler.ru/api/v4/header', $value['GEO_CODE']);
			$data = json_decode($data, TRUE);
			
			//Отправим в функцию для получения пробок
			//$this->loadTraffic($data["traffic"], $value['ID']);
			
			if(isset($data["currencies"]) and is_array($data["currencies"])){
				foreach($data["currencies"] as $keycurrencies => $valuecurrencies) {
					$rec['TITLE'] = 'currencies.'.$valuecurrencies['code'];
					$rec['VALUE'] = $valuecurrencies['value'];
					$rec['CITY_ID'] = $value['ID'];
					$rec['UPDATE'] = time();
					
					$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$rec['TITLE']."' AND CITY_ID = '".$rec['CITY_ID']."'");
					if(!$ifExist) {
						SQLInsert('rambler_weather_value', $rec);
					} else {
						//Обновляем свойства
						$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $rec['VALUE'], $ifExist['VALUE'], $value['ID'], $value['TITLE']);
							
						if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) continue;
						
						$rec['ID'] = $ifExist['ID'];
						SQLUpdate('rambler_weather_value', $rec);
					}
				}
			}
		}
		
	}
	
	//Загрузка раз в час
	function loadDataCycle() {
		$getUniqCityID = SQLSelect("SELECT DISTINCT CITY_ID FROM rambler_weather_value WHERE LINKED_OBJECT != '' OR LINKED_PROPERTY != '' OR LINKED_METHOD != ''");
		
		foreach($getUniqCityID as $value) {
			$url_path = SQLSelectOne('SELECT URL_PATH FROM rambler_weather_city WHERE ID = "'.$value["CITY_ID"].'"');
			$this->loadWeatherNow($url_path['URL_PATH'], 1);
		}
	}
	
	function loadTraffic($data, $id, $cycleupdate = 0) {
		if(empty($data)) return;
		
		foreach($data as $key => $value) {
			$rec['TITLE'] = 'traffic.'.$key;
			$rec['VALUE'] = $value;
			$rec['CITY_ID'] = $id;
			$rec['UPDATE'] = time();
			
			$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$rec['TITLE']."' AND CITY_ID = '".$rec['CITY_ID']."'");
			if(!$ifExist) {
				SQLInsert('rambler_weather_value', $rec);
			} else {
				//Обновляем свойства
				$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $rec['VALUE'], $ifExist['VALUE'], $id);
				
				if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) continue;
				
				$rec['ID'] = $ifExist['ID'];
				SQLUpdate('rambler_weather_value', $rec);
			}
		}
	}
	
	function inday_weather($data, $id, $cycleupdate = 0) {
		if(empty($data)) return;

		$arrayKeyInDay = ['00_00', '03_00', '06_00', '09_00', '12_00', '15_00', '18_00', '21_00', '24_00'];
		
		foreach($data['table_data'] as $key => $value) {
			unset($value['date']);
			foreach($value as $key2 => $value2) {
				foreach($value2 as $key3 => $value3) {
					$rec['TITLE'] = 'inday_weather.'.$key2.'_'.$arrayKeyInDay[$key3];
					$rec['VALUE'] = $value3;
					$rec['CITY_ID'] = $id;
					$rec['UPDATE'] = time();
					
					if(($key2 == 'temperature' || $key2 == 'temp_feels' || $key2 == 'temp_water' ) && $rec['VALUE'] > 0) {
						$rec['VALUE'] = '+'.$rec['VALUE'];
					}
					
					$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$rec['TITLE']."' AND CITY_ID = '".$rec['CITY_ID']."'");
					if(!$ifExist) {
						SQLInsert('rambler_weather_value', $rec);
					} else {
						//Обновляем свойства
						$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $rec['VALUE'], $ifExist['VALUE'], $id);
						
						if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) continue;
						
						$rec['ID'] = $ifExist['ID'];
						SQLUpdate('rambler_weather_value', $rec);
					}
					
				}
			}
		}
	}
	
	
	function loadWeatherNow($url_path = '', $cycleupdate = 0) {
		if($url_path != '') {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city WHERE URL_PATH = '".DBSafe($url_path)."'");
		} else {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city");
		}
		
		foreach($getAllCity as $key => $value) {
			$data = $this->callAPI('https://weather.rambler.ru/api/v3/today/?all_data=0&url_path='.$value['URL_PATH']);
			$data = json_decode($data, TRUE);
			
			//Добавим расчет фазы луны
			$data["date_weather"]["moon_phase_text"] = $this->moonPhaseText($data["date_weather"]["moon_phase"]);
			$data["date_weather"]["wind_direction_text"] = $this->getWindDirectionText($data["date_weather"]["wind_direction"]);
			$data["date_weather"]["geomagnetic_text"] = $this->magneticText($data["date_weather"]["geomagnetic"]);
			$data["date_weather"]["uv_text"] = $this->uvText($data["date_weather"]["uv"]);
			$data["date_weather"]["icon_text"] = $this->iconText($data["date_weather"]["icon"]);
			$data["date_weather"]["roadway_visibility_points"] = $data["date_weather"]["roadway_visibility"]["points"];
			$data["date_weather"]["roadway_visibility_description"] = $data["date_weather"]["roadway_visibility"]["description"];
			$data["date_weather"]["sunset"] = date('H:i:s', strtotime($data["date_weather"]["sunset"]));
			$data["date_weather"]["sunrise"] = date('H:i:s', strtotime($data["date_weather"]["sunrise"]));
			$daylightsec = $data['date_weather']['daylight'];
			$data["date_weather"]["daylight_H_i"] = date_format(new DateTime("@$daylightsec"),'H:i');
			//добавим название города
			$data["date_weather"]["name_town"] = $data["town"]["name"];
			
			
			
			unset($data["date_weather"]["alert_text_short"]);
			unset($data["date_weather"]["date"]);
			
			foreach($data["date_weather"] as $weatherNowKey => $weatherNowValue) {
				if(!is_array($weatherNowValue)) {
					$rec['TITLE'] = 'current_weather.'.$weatherNowKey;
					$rec['VALUE'] = $weatherNowValue;
					$rec['CITY_ID'] = $value['ID'];
					$rec['UPDATE'] = time();
					
					if(($weatherNowKey == 'temperature' || $weatherNowKey == 'temp_feels' || $weatherNowKey == 'temp_water' ) && $weatherNowValue > 0) {
						$rec['VALUE'] = '+'.$weatherNowValue;
					}
					
					$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$rec['TITLE']."' AND CITY_ID = '".$rec['CITY_ID']."'");
					
					if(!$ifExist) {
						SQLInsert('rambler_weather_value', $rec);
					} else {
						//Обновляем свойства
						$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $rec['VALUE'], $ifExist['VALUE'], $value['ID'], $value['TITLE']);
						
						if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) continue;
						
						if(!isset($ifExist['GEO_CODE']) or $ifExist['GEO_CODE'] != $data["town"]["geo_id"]) {
							//Обновим GEO ID он нам понадобится дальше
							SQLExec("UPDATE rambler_weather_city SET GEO_CODE = '".$data["town"]["geo_id"]."' WHERE ID = '".$value['ID']."'");
						}

						$rec['ID'] = $ifExist['ID'];
						SQLUpdate('rambler_weather_value', $rec);					
					}
				}
			} 
			
			//Валюту выгрузим
			$this->loadCurrenciesNow($value['URL_PATH'], $cycleupdate);
			//IP получим
			$this->serverIP($value['ID'], $cycleupdate);
			//Получим прогноз на день
			$this->inday_weather($data, $value['ID'], $cycleupdate);
			//Получим прогноз на 10 дней
			$this->loadWeatherforecast10($value['URL_PATH'], $cycleupdate);
			//Получим гороскоп
			$this->goroskop($value['ID'], $cycleupdate);
		}
	}
	
	function loadWeatherforecast10($url_path = '', $cycleupdate = 0) {
		if($url_path != '') {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city WHERE URL_PATH = '".DBSafe($url_path)."'");
		} else {
			$getAllCity = SQLSelect("SELECT * FROM rambler_weather_city");
		}
		
		foreach($getAllCity as $keyy => $valuee) {
			$data = $this->callAPI('https://weather.rambler.ru/api/v3/ndays/?n=10&url_path='.$valuee['URL_PATH']);
			$data = json_decode($data, TRUE);
			$id=$valuee['ID'];
			
			$arr=array();
		
			foreach ($data['range_weather'] as $key => $value) {
				foreach($value as $name => $val) {
					if (!is_array($val)) {
						
						switch ($name) { 
						case 'date': 
								// Команды
								$val = date("d.m.Y", strtotime($val));
								break;     //выход чтобы дальше не искал, если нашел и выполнил 
						case 'sunset': 
								$val = date('d.m.Y H:i:s', strtotime($val));
								break; 
						case 'sunrise':
								$val = date('d.m.Y H:i:s', strtotime($val));
								break;
						case 'uv':
								$val = $this->uvText($val);
								break;
						case 'daylight':
								$val = date_format(new DateTime("@$val"),'H:i');
								break;
						case 'moon_phase':
								$val = $this->moonPhaseText($val);
								break;			
						case 'geomagnetic':
								$val = $this->magneticText($val);
								break;		
															
						}
						
					$arr[] = array('TITLE' => 'forecast.'.$key.'_'.$name, 'VALUE' => $val, 'CITY_ID' => $id, 'UPDATE' => time());
					}
					else {
						foreach ($value['forecast'] as $partOfDay => $value2) {
							foreach ($value2 as $valuename => $itogValue) {
							
								switch ($valuename) { 
									case 'temperature': 
											// Команды
											if($itogValue > 0) {
											$itogValue = '+'.$itogValue;
											}				
											break;     //выход чтобы дальше не искал, если нашел и выполнил 
									case 'wind_direction': 
											$itogValue = $this->getWindDirectionText($itogValue);
											break; 
									case 'icon':
											$arr[] = array('TITLE' => 'forecast.'.$key.'_'.$partOfDay.'_'.$valuename.'_text', 'VALUE' => $this->iconText($itogValue), 'CITY_ID' => $id, 'UPDATE' => time());
											break;
											
								}
								
								$arr[] = array('TITLE' => 'forecast.'.$key.'_'.$partOfDay.'_'.$valuename, 'VALUE' => $itogValue, 'CITY_ID' => $id, 'UPDATE' => time());
							}
						}
					}
				}
				
			}
					
			foreach ($arr as $key => $value)	{
			$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$value['TITLE']."' AND CITY_ID = '".$value['CITY_ID']."'");
					
			if(!is_array ($ifExist)) {
				SQLInsert('rambler_weather_value', $value);
				
			} else {
				//Обновляем свойства
				$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $value['VALUE'], $ifExist['VALUE'], isset($value['ID']) ? $value['ID'] : '', $value['TITLE']);
				$value['ID'] = $ifExist['ID'];		
				if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) continue;
				SQLUpdate('rambler_weather_value', $value);
				}
			}
		}
	}


	function goroskop($id, $cycleupdate = 0) {
			
		$horo=array(
		  "aries" => "Овен",
		  "taurus" => "Телец",
		  "gemini" => "Близнецы",
		  "cancer" => "Рак",
		  "leo" => "Лев",
		  "virgo" => "Дева",
		  "libra" => "Весы",
		  "scorpio" => "Скорпион",
		  "sagittarius" => "Стрелец",
		  "capricorn" => "Козерог",
		  "aquarius" => "Водолей",
		  "pisces" => "Рыбы",);
	
	
		$arr=array();
		foreach($horo as $znak => $znak_rus) {
			$data = $this->callAPI('https://horoscopes.rambler.ru/api/front/v3/horoscope/general/'.$znak.'/tomorrow/');
			$data = json_decode($data, TRUE);
			
			foreach ($data['content']['text'] as $key => $value) {
				$arr[] = array('TITLE' => 'goroskop.'.$znak.'_tomorrow', 'VALUE' => $value["content"], 'CITY_ID' => $id, 'UPDATE' => time());
			}
		}
		foreach($horo as $znak => $znak_rus) {
			$data = $this->callAPI('https://horoscopes.rambler.ru/api/front/v3/horoscope/general/'.$znak.'/today/');
			$data = json_decode($data, TRUE);
			
			foreach ($data['content']['text'] as $key => $value) {
				$arr[] = array('TITLE' => 'goroskop.'.$znak.'_today', 'VALUE' => $value["content"], 'CITY_ID' => $id, 'UPDATE' => time());
			}
		}
		foreach ($arr as $key => $value)	{
			$ifExist = SQLSelectOne("SELECT * FROM rambler_weather_value WHERE TITLE = '".$value['TITLE']."' AND CITY_ID = '".$value['CITY_ID']."'");
					
			if(!$ifExist) {
				SQLInsert('rambler_weather_value', $value);
				
			} else {
				//Обновляем свойства
				$this->setPropByNewValue($ifExist['LINKED_OBJECT'], $ifExist['LINKED_PROPERTY'], $ifExist['LINKED_METHOD'], $value['VALUE'], $ifExist['VALUE'], isset($value['ID']) ? $value['ID'] : '', $value['TITLE']);
				$value['ID'] = $ifExist['ID'];		
				if($cycleupdate != 0 && empty($ifExist['LINKED_OBJECT']) && empty($ifExist['LINKED_PROPERTY']) && empty($ifExist['LINKED_METHOD'])) continue;
				SQLUpdate('rambler_weather_value', $value);
			}
		}
				
			
	}
	

	function setPropByNewValue($object = '', $property = '', $method = '', $newvalue, $oldvalue, $city_id = '', $city_title = '') {
		if(!empty($object) && !empty($property) && ($newvalue != $oldvalue || $newvalue != gg($object.'.'.$property))) {
			sg($object.'.'.$property, $newvalue);
		}
		if(!empty($object) && !empty($method) && $newvalue != $oldvalue) {
			cm($object.'.'.$method, array(
				'NEW_VALUE' => $newvalue,
				'OLD_VALUE' => $oldvalue,
				'CITY_ID' => $city_id,
				'CITY_NAME' => $city_title,
			));
		}
	}
	
	function usual(&$out) {
		//Обработка AJAX 
		global $request;
		global $q;
		
		if($this->ajax == 1 && $request == 'whereiam') {
			$data = $this->whereiam();
			$data = json_decode($data, TRUE);
			
			$data = $this->callAPI('https://weather.rambler.ru/api/v3/suggest/?query='.urlencode($data['name']).'&count=1');
			
			echo $data;
			die();
    	}
		
		if($this->ajax == 1 && $request == 'findcity' && !empty($q)) {
			$data = $this->callAPI('https://weather.rambler.ru/api/v3/suggest/?query='.urlencode($q).'&count=10');
	
			echo $data;
			die();
    	}
		
		global $url_path;

		if(isset($url_path)) {
			$city = SQLSelectOne('SELECT * FROM rambler_weather_city WHERE URL_PATH = "'.DBSafe($url_path).'"');
			$data = SQLSelectOne('SELECT * FROM rambler_weather_value WHERE CITY_ID = "'.DBSafe($city['ID']).'" AND LINKED_OBJECT != "" AND LINKED_PROPERTY != ""');
			
			foreach($city as $key => $value) {
				$city['CITY_'.$key] = $value;
				unset($city[$key]);
			}
			
			$count = 0;
			
			foreach($data as $key => $value) {
				if($key == 'TITLE' && $value == 'current_weather.temperature') {
					$city['DATA_TEMP'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.roadway_visibility_description') {
					$city['DATA_READDESC'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.icon') {
					$city['DATA_ICON'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.icon_text') {
					$city['DATA_ICON_TEXT'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.temp_feels') {
					$city['DATA_TEMP_FEELS'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.wind_direction_text') {
					$city['DATA_WIND_DER'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.wind_speed') {
					$city['DATA_WIND'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.uv') {
					$city['DATA_UV'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.pressure_mm') {
					$city['DATA_PRESS'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.moon_phase_text') {
					$city['DATA_MOON_PH'] = $data['VALUE'];
					$count++;
				} else if($key == 'TITLE' && $value == 'current_weather.geomagnetic_text') {
					$city['DATA_GEOMAG'] = $data['VALUE'];
					$count++;
				}
			}
			
			echo '<pre>';
			var_dump($city);
		}
	}
	
	function callAPI($url, $geocode = 0) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		if($geocode != 0) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Cookie: geoid='.$geocode
			));
			//curl_setopt($ch, CURLOPT_COOKIEFILE, '/var/www/html/cms/cached/rambler.txt');
		}
		
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		$html = curl_exec($ch);
		curl_close($ch);
		 
		return $html;
	}


	function whereiam() {
		$data = $this->callAPI('https://weather.rambler.ru/location/autodetect');
		//$data = json_decode($data, TRUE);
		return $data;
	}

	function DeleteLinkedProperties($city_id = 0) {
		if($city_id != 0) {
			$properties = SQLSelect("SELECT * FROM rambler_weather_value WHERE CITY_ID = '".$city_id."' AND LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");
		} else {
			$properties = SQLSelect("SELECT * FROM rambler_weather_value WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");
		}
		
		if (!empty($properties)) {
			foreach ($properties as $prop) {
				removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
			}
		}
	}
	
	function processSubscription($event, $details='') {
		if($event=='MINUTELY' && date('i', time()) % 20 == 0) {
			$this->loadDataCycle();
		}
	}
	
	function install($data='') {
		subscribeToEvent($this->name, 'MINUTELY');
		
		parent::install();
	}
	
	function uninstall() {
		unsubscribeFromEvent($this->name, 'MINUTELY');
		
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
        rambler_weather_value: VALUE varchar(1024) NOT NULL DEFAULT ''
        rambler_weather_value: UPDATE varchar(255) NOT NULL DEFAULT ''
        rambler_weather_value: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
        rambler_weather_value: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
        rambler_weather_value: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
EOD;
		parent::dbInstall($data);
   }
}
