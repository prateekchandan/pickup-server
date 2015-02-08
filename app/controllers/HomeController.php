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
		try {
			$address = json_decode(file_get_contents($address));
		} catch (Exception $e) {
			return 0;
		}
		$path  = $address->routes;
		if(sizeof($path)==0)
			return 0;
		return $path;
	}

	public function journey_add(){
		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'journey_time' , 'margin_after' , 'margin_before' , 'preference'];
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
		if(!is_null($journey))
			return Error::make(1,9);

		$journey = new Journey;
		$journey->start_lat = Input::get('start_lat');
		$journey->start_long = Input::get('start_long');
		$journey->end_lat = Input::get('end_lat');
		$journey->end_long = Input::get('end_long');
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
	

}
