<?php

class HomeController extends BaseController {

	/*
	|--------------------------------------------------------------------------
	| Default Home Controller
	|--------------------------------------------------------------------------
	|
	| You may wish to use controllers instead of, or in addition to, Closure
	| based routes. That's great! Here is an example controller method to
	| get you started. To route to this controller, just add the route:
	|
	|	Route::get('/', 'HomeController@showWelcome');
	|
	*/

	public $debug = 0;

	public function distance($lat1, $lon1, $lat2, $lon2, $unit = "K") {
	 
	  $theta = $lon1 - $lon2;
	  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	  $dist = acos($dist);
	  $dist = rad2deg($dist);
	  $miles = $dist * 60 * 1.1515;
	  $unit = strtoupper($unit);
	 
	  if ($unit == "K") {
	    return ($miles * 1.609344);
	  } else if ($unit == "N") {
	      return ($miles * 0.8684);
	    } else {
	        return $miles;
	      }
	}

	public function get_address($latitude = 19.12 , $longitude = 72.91)
	{
	
		try {
			$address = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude"));
		} catch (Exception $e) {
			return 0;
		}

		$results=$address->results;

		if(sizeof($results) == 0)
			return 0;

		$results = $results[0]->address_components;

		$country = "";
		$state = "";
		$city = "";
		$locality = "";
		$subl = "";

		foreach ($results as $row) {

			if($row->types[0] == 'country')
				$country = $row->long_name;

			if($row->types[0] == 'administrative_area_level_1')
				$state = $row->long_name;

			if($row->types[0] == 'administrative_area_level_2')
				$city = $row->long_name;

			if($row->types[0] == 'locality')
				$locality = $row->long_name;

			if($row->types[0] == 'sublocality_level_1')
				$subl = $row->long_name;
		}


		if($city == "" && $locality!=""){
			$city = $locality;
			$locality = $subl;
		}

		if($subl != ""){
			$locality = $subl;
		}

		return array('country'=>$country , 'city' => $city , 'state' => $state , 'locality'=>$locality);
	}


	public function find_path($lat1 = 0 , $log1 = 0 , $lat2 = 0 , $log2 = 0 , $waypoints= array()){
		$address = "https://maps.googleapis.com/maps/api/directions/json?origin=$lat1,$log1&destination=$lat2,$log2&waypoints=";
		foreach ($waypoints as $key => $value) {
			$address .= $value[0].','.$value[1].'|';
		}
		$address .= "&alternatives=true&sensor=false";
		try {
			$address = json_decode(file_get_contents($address));
		} catch (Exception $e) {
			return 0;
		}
		$path  = $address->routes;
		function cmp($a,$b){
			$d1 = 0;
			$d2 = 0;
			foreach ($a as $key => $legobj) {
				$d1 += $legobj->distance->value;
			}
			foreach ($b as $key => $legobj) {
				$d2 += $legobj->distance->value;
			}
			return $d1 < $d2;
		}
		usort($path,'cmp');

		if(sizeof($path)==0)
			return 0;
		return $path;
	}

	public function journey_add(){
		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'journey_time' , 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'));
		if($path == 0){
			return Error::make(0,3);
		}
		$distance  = $path[0]->legs[0]->distance->value;
		if($distance > 100000)
			return Error::make(1,4);
		
		$user = User::find(Input::get('user_id'));
		if(is_null($user))
			return Error::make(1,1);

		if (DateTime::createFromFormat('Y-m-d G:i:s', Input::get('journey_time')) !== FALSE) {
		  $timestamp = (array)DateTime::createFromFormat('Y-m-d G:i:s', Input::get('journey_time'));
		  $timestamp = $timestamp['date'];
		}
		else{
			return Error::make(1,5);
		}
		$sec = strtotime($timestamp);
		$timenow = time();
		if($timenow > $sec)
			return Error::make(1,6);

		if(!(is_numeric(Input::get('margin_after')) && is_numeric(Input::get('margin_before'))))
			return Error::make(1,7);

		if(Input::get('margin_after') > 60 || Input::get('margin_before') >60 || Input::get('margin_after') < 0 || Input::get('margin_before') < 0)
			return Error::make(1,7);
		
		if(!is_numeric(Input::get('preference')) || Input::get('preference') > 5 || Input::get('preference') < 1 )
			return Error::make(1,8);
		
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*3);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*3);;

		$journey = Journey::where('id' , '=' , Input::get('user_id'))->where('journey_time' , '>' , $t2 )->where('journey_time' , '<' , $t1 )->first();
		if($this->debug > 0)
		if(!is_null($journey))
			return Error::make(1,9);

		$journey = new Journey;
		$journey->start_lat = Input::get('start_lat');
		$journey->start_long = Input::get('start_long');
		$journey->end_lat = Input::get('end_lat');
		$journey->end_long = Input::get('end_long');
		$journey->start_text = Input::get('start_text');
		$journey->end_text = Input::get('end_text');
		$journey->id = Input::get('user_id');
		$journey->journey_time = $timestamp;
		$journey->margin_before = Input::get('margin_before');
		$journey->margin_after = Input::get('margin_after');
		$journey->preference = Input::get('preference');


		try {
			$journey->save();
			return Error::success("Journey successfully Registered");
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}


	public function journey_edit($journey_id){

		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);

		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'journey_time' , 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'));
		if($path == 0){
			return Error::make(0,3);
		}
		$distance  = $path[0]->legs[0]->distance->value;
		if($distance > 100000)
			return Error::make(1,4);
		
		$user = User::find(Input::get('user_id'));
		if(is_null($user))
			return Error::make(1,1);

		if (DateTime::createFromFormat('Y-m-d G:i:s', Input::get('journey_time')) !== FALSE) {
		  $timestamp = (array)DateTime::createFromFormat('Y-m-d G:i:s', Input::get('journey_time'));
		  $timestamp = $timestamp['date'];
		}
		else{
			return Error::make(1,5);
		}
		$sec = strtotime($timestamp);
		$timenow = time();
		if($timenow > $sec)
			return Error::make(1,6);

		if(!(is_numeric(Input::get('margin_after')) && is_numeric(Input::get('margin_before'))))
			return Error::make(1,7);

		if(Input::get('margin_after') > 60 || Input::get('margin_before') >60 || Input::get('margin_after') < 0 || Input::get('margin_before') < 0)
			return Error::make(1,7);
		
		if(!is_numeric(Input::get('preference')) || Input::get('preference') > 5 || Input::get('preference') < 1 )
			return Error::make(1,8);
		
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*3);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*3);;

		$journey = Journey::where('id' , '=' , Input::get('user_id'))->where('journey_id' , '!=' , $journey_id)->where('journey_time' , '>' , $t2 )->where('journey_time' , '<' , $t1 )->first();
		if($this->debug > 0)
		if(!is_null($journey))
			return Error::make(1,9);

		try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'start_lat' => Input::get('start_lat'),
				'start_long' => Input::get('start_long'),
				'end_lat' => Input::get('end_lat'),
				'end_long' => Input::get('end_long'),
				'start_text' => Input::get('start_text'),
				'end_text' => Input::get('end_text'),
				'id' => Input::get('user_id'),
				'journey_time' => $timestamp,
				'margin_before' => Input::get('margin_before'),
				'margin_after' => Input::get('margin_after'),
				'preference' => Input::get('preference'),
			));

			return Error::success("Journey successfully Edited");
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	public function journey_delete($journey_id){
		$journey = Journey::where('journey_id','=',$journey_id)->delete();
		return Error::success("Journey successfully Deleted");
	}
	
	function MakeGroups(){
		
		$t1 = date('Y-m-d G:i:s',time());
		$t2 = date('Y-m-d G:i:s',time()+600);
		$pending = Journey::where('journey_time' , '>' , $t1 )->where('journey_time' , '<' , $t2 )->get();
		
		$l = sizeof($pending);
		for ($i=0; $i < $l; $i++) { 
			$pending[$i]->group = 0;
		}
		$groups = array();
		for ($i=0; $i < $l; $i++) { 
			$mind = 99999999;
			$mini = $i;
			$path = $this->find_path($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$i]->end_lat , $pending[$i]->end_long);
			if($pending[$i]->group == 1)
				continue;

			for ($j=$i+1; $j < $l; $j++) {
				
				if($pending[$i]->id == $pending[$j]->id)
					continue;
				if($this->distance($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$j]->start_lat , $pending[$j]->start_long) > 3)
					continue;
				if($this->distance($pending[$i]->end_lat , $pending[$i]->end_long , $pending[$j]->end_lat , $pending[$j]->end_long) > 3)
					continue;
				
				$timediff = abs(strtotime($pending[$i]->journey_time) - strtotime($pending[$j]->journey_time)) / (60*60);
				if($timediff > 1)
					continue;

				$p = array();
				$p[0] = $this->find_path($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$i]->end_lat , $pending[$i]->end_long , 
					array(
						array($pending[$j]->start_lat,$pending[$j]->start_long),
						array($pending[$j]->end_lat,$pending[$j]->end_long)
						)
					);
				$p[1] = $this->find_path( $pending[$j]->start_lat,$pending[$j]->start_long, $pending[$i]->end_lat , $pending[$i]->end_long , 
					array(
						array($pending[$i]->start_lat , $pending[$i]->start_long),
						array($pending[$j]->end_lat,$pending[$j]->end_long)
						)
					);
				$p[2] = $this->find_path($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$j]->end_lat,$pending[$j]->end_long, 
					array(
						array($pending[$j]->start_lat,$pending[$j]->start_long),
						array($pending[$i]->end_lat , $pending[$i]->end_long )
							)
					);
				$p[3] = $this->find_path( $pending[$j]->start_lat,$pending[$j]->start_long, $pending[$j]->end_lat , $pending[$j]->end_long , 
					array(
						array($pending[$i]->start_lat , $pending[$i]->start_long),
						array($pending[$i]->end_lat , $pending[$i]->end_long )
						)
					);
				$d = array();
				for ($k=0; $k < 3; $k++) { 
					if($p[$k]==0)
						$p[$k] = json_decode('[{"legs" : [{"distance" : {"value" : 9999999999}}]}]');
				
					$p[$k] = $p[$k][0];
					$d[$k] = 0;
					foreach ($p[$k]->legs as $key => $leg) {
						$d[$k] += $leg->distance->value;
					}
				}
				$mi = 0; $md = $d[0];
				for ($k=0; $k < 3; $k++) { 
					if($d[$k] < $d[$mi]){
						$mi = $k;
						$md = $d[$k];
					}
				}

				if($md < $mind){
					$mind = $md;
					$mini = $j;
					$path = $p[$mi];
				}
			}
			array_push($groups, array($pending[$i]->id , $pending[$mini]->id , $path));
			$pending[$i]->group =1;
			$pending[$mini]->group =1;
		}
		foreach ($groups as $key => $group) {
			$u1 = User::where('id','=',$group[0])->first() ;
			$u2 = User::where('id','=',$group[1])->first() ;
			$path = $group[2];
			$jpair = new FinalJourney;
			$jpair->u1 = $group[0];
			$jpair->u2 = $group[1];
			if($group[0] == $group[1]){
				$path = $path[0];
			}
			$jpair->path = json_encode($path);
			$jpair->save();
			$u1msg = array();
			$u1msg['journey_id'] = $jpair->id;
			$u1msg['name'] = $u2->first_name;
			if($group[0]==$group[1]){
				$u1msg['type'] = 0;
				$collection =  PushNotification::app('Pickup')
                ->to($u1->registration_id)
                ->send(json_encode($u1msg));
			}
			else{
				$u1msg['type'] = 1;
				$u1msg['name'] = $u2->first_name;
				$collection = PushNotification::app('Pickup')
	                ->to($u1->registration_id)
	                ->send(json_encode($u1msg));
	            $u1msg['name'] = $u1->first_name;
				$collection1 = PushNotification::app('Pickup')
	                ->to($u2->registration_id)
	                ->send(json_encode($u1msg));
            }
            foreach ($collection->pushManager as $push) {
		    	$response = $push->getAdapter()->getResponse();
		    	print_r($response);
			}
			if(isset($collection1)){
				foreach ($collection1->pushManager as $push) {
		    	$response = $push->getAdapter()->getResponse();
		    	print_r($response);
				}
			}
		}
		
	}

	public function get_journey($id=0)
	{
		$jpair = FinalJourney::find($id);
		if(is_null($jpair)){
			return Error::make(1,10);
		}
		$jpair->u1 = User::find($jpair->u1);
		$jpair->u2 = User::find($jpair->u2);
		$jpair->path = json_decode($jpair->path);
		return $jpair;
	}

}
