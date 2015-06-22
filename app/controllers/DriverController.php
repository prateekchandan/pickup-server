<?php

class DriverController extends BaseController {

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
	public function add()
	{
		$requirements = ['driver_name'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$driver = new Driver;
		$driver->group_id = Input::get('group_id');
		$driver->driver_name = Input::get('driver_name');
		
		try {
			$driver->save();
			return Error::success("Driver successfully added" , array("driver_id" => $driver->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	public function give_driver_group($driver_id=0)
	{
		$requirements = ['group_id'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,19);
		}
		try {
			Driver::where('driver_id','=',$driver_id)->update(array(
				'group_id' => intval(Input::get('group_id')),
				));
			Group::where('group_id','=',intval(Input::get('group_id')))->update(array(
				'driver_id' => $driver_id,
				));
			return Error::success("Group successfully added" , array("driver_id" => intval($driver_id)));
		} 
		catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	

	public function get($driver_id=0)
	{
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,1);
		}
		return $driver;
	}

	public function modify_location($driver_id=0)
	{
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,10);
		}
		$requirements = ['position'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$new_coordinate_array = explode(',',Input::get('position'));
		$old_coordinate_array = explode(',',$driver->current_pos);
		$distance_increment = self::distance(floatval($new_coordinate_array[0]),
											floatval($new_coordinate_array[1]),
											floatval($old_coordinate_array[0]),
											floatval($old_coordinate_array[1]));
		if ($driver->driver_status=='occupied')
		{
			$group = Group::where('group_id','=',$driver->group_id)->first();
			$journey_ids = json_decode($group->journey_ids);
			foreach ($journey_ids as $journey_id)
			{
				$journey = Journey::where('journey_id','=',$journey_id)->first();
				$new_distance = intval($journey->distance_travelled)+$distance_increment;
				try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'distance_travelled' => $distance_travelled,
				));
			} 
			catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
			}
			}
		}
		
		try {
			Driver::where('driver_id','=',$driver_id)->update(array(
				'current_pos' => Input::get('position'),
				));
			//$user->id = 10;
			//$this->sendmail($user);
			return Error::success("Driver location changed" , array("driver_id" => $driver_id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function group_enlist($driver_id=0)
	{
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,10);
		}
		$group = Group::where('group_id','=',$driver->group_id)->first();
		if(is_null($group)){
			return Error::make(1,18);
		}
		$corresponding_ids=json_decode($group->journey_ids);
		$final_data=array();
		for ($i=0;$i<sizeof($corresponding_ids);$i++)
		{
			$temp_id=intval($corresponding_ids[$i]);
			$temp_journey = Journey::where('journey_id','=',$temp_id)->first();
			if(is_null($temp_journey)){
				return Error::make(1,10);
			}
			$temp_journey->path=NULL;
			$final_data[$i]=$temp_journey;
			$user_data = User::find($temp_journey->id);
			if(is_null($user_data)){
				return Error::make(1,1);
			}
			$user_data->home_to_office=NULL;
			$user_data->office_to_home=NULL;
			$final_data[$i]->user_data=$user_data;
		}
		$jsonobject = array("error" => 0, "message" => "ok" , "mates"=>$final_data);
		return $jsonobject;
	}
	public function end_journey($driver_id=0)
	{
		$requirements = ['journey_id'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,10);
		}
		$group = Group::where('group_id','=',$driver->group_id)->first();
		if(is_null($group)){
			return Error::make(1,18);
		}

		$corresponding_ids=json_decode($group->journey_ids);
		if (!in_array(intval(Input::get('journey_id')), $corresponding_ids)) {
			return Error::make(1,20); 
		}
		if (($key = array_search(intval(Input::get('journey_id')), $corresponding_ids)) !== false) {
			array_splice($corresponding_ids, $key, 1);
		}
		try {
			Group::where('group_id','=',$driver->group_id)->update(array(
				'journey_ids' => json_encode($corresponding_ids),
				));
			//$user->id = 10;
			//$this->sendmail($user);
			return Error::success("Person removed" , array("journey_id removed" => intval(Input::get('journey_id'))));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}

	}
	public function allocate_driver()
	{
		$drivers_occupied_now=array();
		$t1 = date('Y-m-d G:i:s',time()+900);
		$t2 = date('Y-m-d G:i:s',time());
		$groups = Group::whereNull('driver_id')->where('journey_time' , '<' , $t1 )->where('journey_time','>',$t2)->orderBy('journey_time','asc')->get();
		
		foreach ($groups as $group)
		{
			$drivers = Driver::where('driver_status','=','vacant')->get();
			$path = json_decode($group->path_waypoints);

			$start_lat = $path->startwaypoints[0][0];
			$start_long = $path->startwaypoints[0][1];
			$closest_driver_id=0;
			$closest_distance=100000;

			foreach($drivers as $driver)
			{
				$driver_pos = $driver->current_pos;
				$driver_lat = floatval(substr($driver_pos,0,strpos($driver_pos,',')));
				$driver_long = floatval(substr($driver_pos,strpos($driver_pos, ',')+1));
				if (self::distance($start_lat,$start_long,$driver_lat,$driver_long)<$closest_distance)
				{
					$closest_distance=self::distance($start_lat,$start_long,$driver_lat,$driver_long);
					$closest_driver_id=intval($driver->driver_id);
				}
			}
			if ($closest_driver_id!=0)
			{
				$people_so_far=json_decode($group->journey_ids);
				foreach ($people_so_far as $journey_id1) {
				$journey_details = Journey::where('journey_id','=',$journey_id1)->first();
				$user = User::where('id' , '=',intval($journey_details->id))->first();
				$uMsg = array();
				$uMsg['type'] = 11;
				$uMsg['data'] = array('driver_id'=>$closest_driver_id);
				$uMsg['message'] = "Driver allocated!";
				$collection=PushNotification::app('Pickup')
	            	->to($user->registration_id)
	            	->send(json_encode($uMsg));
	            	foreach ($collection->pushManager as $push) {
    		$success = $push->getAdapter()->getResponse();
				}
				$data = print_r($success,true);
	            	self::log_data($data);
				}
				try {
					Driver::where('driver_id','=',$closest_driver_id)->update(array(
						'group_id' => intval($group->group_id),
						'driver_status' => 'occupied',
						));
					Group::where('group_id','=',$group->group_id)->update(array(
						'driver_id' => $closest_driver_id,
						));
					array_push($drivers_occupied_now,$closest_driver_id);
				}

				catch(Exception $e) {
					return Error::make(101,101,$e->getMessage());
				}
			}
		}
		return Error::success("Drivers allocated" , array("drivers_occupied" => $drivers_occupied_now));
	}
	public function arbit_stuff()
	{
		self::log_data(Input::get('hottest'));
		return "yolo";
	}
}
