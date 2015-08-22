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
	public function driver_gcm_add(){
		$requirements = ['reg_id' , 'driver_id'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$driver = Driver::where('driver_id','=',intval(Input::get('driver_id')))->first();
		//$user = User::find(Input::get('driver_id'));
		if(is_null($driver)){
			return Error::make(1,19);
		}
		$driver->registration_id = Input::get('reg_id');
		try {
			Driver::where('driver_id','=',intval(Input::get('driver_id')))->update(array(
				'registration_id' => $driver->registration_id,
				));
			return Error::success("Registration ID successfully added");
		}
		catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
		
	}
	public function add()
	{
		$requirements = ['driver_name','phone','username','password'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$driver = new Driver;
		//$driver->group_id = Input::get('group_id');
		$driver->username=Input::get('username');
		$driver->password=Input::get('password');
		$driver->phone = Input::get('phone');
		$driver->driver_name = Input::get('driver_name');
		$driver->images = json_encode(array('profile_picture'=>"",'address_proof'=>"",
											'license'=>""));
		$driver->last_ping = date('Y-m-d G:i:s', time());
		try {
			$driver->save();
			return Error::success("Driver successfully added" , array("driver_id" => $driver->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function driver_login()
	{
		$requirements = ['username','password'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);	
		$driver = Driver::where('username','=',Input::get('username'))->first();
		if (is_null($driver))
			return Error::make(1,33);
		if (strcmp($driver->password, Input::get('password'))==0)
			return Error::success('Login successful!',array('driver'=>$driver));
		else
			return Error::make(1,34);
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
	public function get_detailed_group($driver_id)
	{
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		$final_data = array();
		if (is_null($driver))
			return Error::make(1,19);
		if (is_null($driver->group_id))
			return Error::make(1,40);
		else
		{
			$group = Group::where('group_id','=',$driver->group_id)->first();
			$group->path=NULL;
			$final_data['group_details'] = $group;
			$final_data['journey_details'] = array();
			$final_data['user_details'] = array();
			foreach (json_decode($group->journey_ids) as $journey_id) {
				$journey = Journey::where('journey_id','=',$journey_id)->first();
				$journey->path=NULL;
				$journey->path2=NULL;
				$journey->path3=NULL;
				array_push($final_data['journey_details'], $journey);
				$user = User::where('id','=',$journey->id)->first();
				$user->home_to_office=NULL;
				$user->office_to_home=NULL;
				array_push($final_data['user_details'], $user);
			}
			return Error::success('Details of journey',array('final_data'=>$final_data));
		}
	}
	public function picked_up_person($group_id)
	{
		$requirements = ['journey_id','pickup_location'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$journey_id = intval(Input::get('journey_id'));
		$group = Group::where('group_id','=',$group_id)->first();
		if (is_null($group))
			return Error::make(1,17);
		$people_so_far = json_decode($group->journey_ids);
		$people_on_ride = json_decode($group->people_on_ride);
		$event_sequence = json_decode($group->event_sequence);
		$status = $group->event_status;
		$completed = json_decode($group->completed);
		if (in_array($journey_id, $completed))
			Error::make(1,41);
		if (!in_array($journey_id, $people_on_ride))
		{
			array_push($people_on_ride, $journey_id);
			$journey = Journey::where('journey_id','=',$journey_id)->first();
			$user = User::where('id','=',$journey->id)->first();
			$push_data = array('user_id'=>intval($journey->id),'user_name'=>$user->first_name);
			self::send_push($people_so_far,14,$push_data);
			array_push($event_sequence->journey_ids,$journey_id);
			array_push($event_sequence->points,Input::get('pickup_location'));
		}
		else
			return Error::success("Person already in the car!",array('journey_id'=>$journey_id));
		try {
			if (sizeof($people_on_ride)==1)
				$status = "started";
			Group::where('group_id','=',$group_id)->update(array(
				'people_on_ride' => json_encode($people_on_ride),
				'event_status' => $status,
				'event_sequence' => json_encode($event_sequence),
				));
			Journey::where('journey_id','=',$journey_id)->update(array(
				'pickup_location'=>Input::get('pickup_location'),
				));
			} 
			catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
			}
		return Error::success("Person picked up!",array('journey_id'=>$journey_id));
	}	

	public function get($driver_id=0)
	{
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,19);
		}
		$driver['error']=0;
		$driver['message']="ok";
		return $driver;
	}
	public function driver_periodic_route($driver_id)
	{
		$requirements = ['position','event_ids'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$positions = self::modify_location($driver_id,Input::get('position'));
		$pending_events = self::driver_get_pending_events($driver_id,Input::get('event_ids'));
		return Error::success('periodic data',array('positions'=>$positions,
													'pending_events'=>$pending_events));
	}
	public function modify_location($driver_id=0,$position)
	{
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,19);
		}
		if (strcmp($driver->phone_status, 'dead')==0)
		{
			$driver->phone_status='alive';
			try {
				Driver::where('driver_id','=',$driver_id)->update(array(
					'last_ping' =>  date('Y-m-d G:i:s', time()),
					'phone_status' => 'alive',
					));
			} 
			catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
			}
		}
		$new_coordinate_array = explode(',',$position);
		$old_coordinate_array = explode(',',$driver->current_pos);
		$distance_increment = self::distance(floatval($new_coordinate_array[0]),
											floatval($new_coordinate_array[1]),
											floatval($old_coordinate_array[0]),
											floatval($old_coordinate_array[1]));
		if ($driver->driver_status=='occupied')
		{
			$group = Group::where('group_id','=',$driver->group_id)->first();
			$journey_ids = json_decode($group->journey_ids);
			$people_on_ride = json_decode($group->people_on_ride);
			foreach ($journey_ids as $journey_id)
			{
				$journey = Journey::where('journey_id','=',$journey_id)->first();
				$user = User::where('id','=',$journey->id)->first();
				if (!in_array($journey_id, $people_on_ride))
				{
					$user_coordinate_array = explode(',',$user->current_pos);
					$distance = self::distance(floatval($new_coordinate_array[0]),
												floatval($new_coordinate_array[1]),
												floatval($user_coordinate_array[0]),
												floatval($user_coordinate_array[1]));
					if ($distance<0.5)
						self::send_push(array($journey_id,),12,array('driver_id'=>$driver_id));
				}
				$new_distance = intval($journey->distance_travelled)+$distance_increment;
				try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'distance_travelled' => $new_distance,
				));
			} 
			catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
			}
			}
		}
		
		try {
			Driver::where('driver_id','=',$driver_id)->update(array(
				'current_pos' => $position,
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
		$corresponding_ids=json_decode($group->people_on_ride);
		$final_data=array();
		for ($i=0;$i<sizeof($corresponding_ids);$i++)
		{
			$temp_id=intval($corresponding_ids[$i]);
			$temp_journey = Journey::where('journey_id','=',$temp_id)->first();
			if(is_null($temp_journey)){
				return Error::make(1,10);
			}
			$temp_journey->path=NULL;
			$temp_journey->path2=NULL;
			$temp_journey->path3=NULL;
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
	public function end_journey($group_id=0)
	{
		$requirements = ['journey_id','drop_location','app_distance'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$journey_id=intval(Input::get('journey_id'));
		$group = Group::where('group_id','=',$group_id)->first();
		if(is_null($group) || is_null($group->driver_id)){
			return Error::make(1,18);
		}
		$driver_id = intval($group->driver_id);
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if(is_null($driver)){
			return Error::make(1,10);
		}
		$status=$group->event_status;
		$corresponding_ids=json_decode($group->people_on_ride);
		$people_so_far=json_decode($group->journey_ids);
		$completed=json_decode($group->completed);
		$event_sequence=json_decode($group->event_sequence);
		if (!in_array($journey_id, $corresponding_ids)) {
			return Error::make(1,20); 
		}
		array_push($completed, $journey_id);
		if (($key = array_search($journey_id, $corresponding_ids)) !== false) {
			array_splice($corresponding_ids, $key, 1);
		}
		array_push($event_sequence->journey_ids, $journey_id);
		array_push($event_sequence->points,Input::get('drop_location'));
		try {
			if (sizeof($corresponding_ids)==0)
			{
				$status="completed";
				Driver::where('driver_id','=',$driver_id)->update(array(
				'driver_status' => 'vacant',
				));
			}
			Group::where('group_id','=',$group_id)->update(array(
				'people_on_ride' => json_encode($corresponding_ids),
				'event_status' => $status,
				'completed' => json_encode($completed),
				'event_sequence' => json_encode($event_sequence),
				));
			Journey::where('journey_id','=',$journey_id)->update(array(
				'drop_location' => Input::get('drop_location'),
				'distance_travelled_app' => floatval(Input::get('app_distance')),
				));
			$fare=0;
			try
			{
				$fare = CostCalc::calculate($journey_id);
			}
			catch(Exception $e){
				Error::make(1,43);
			}
			Journey::where('journey_id','=',$journey_id)->update(array(
				'fare'=>$fare,
				));
			$journey = Journey::where('journey_id','=',$journey_id)->first();
			$user = User::where('id','=',$journey->id)->first();
			$push_data = array('user_id'=>intval($journey->id),'user_name'=>$user->first_name,'fare'=>$fare);
			self::send_push($people_so_far,15,$push_data);
			//$user->id = 10;
			//$this->sendmail($user);
			return Error::success("Person completed his journey!" , array("journey_id removed" => intval(Input::get('journey_id')),"fare"=>$fare));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}

	}
	public function allocate_driver()
	{
		$alive_drivers = Driver::where('phone_status','=','alive')->get();
		foreach ($alive_drivers as $alive) {
			if (time()-strtotime($alive->last_ping)>900)
			{
				Driver::where('driver_id','=',$alive->driver_id)->update(array(
						'phone_status' => 'dead',
						));
			}
		}
		
		$drivers_occupied_now=array();
		$t1 = date('Y-m-d G:i:s',time()+900);
		$t2 = date('Y-m-d G:i:s',time());
		//$groups = Group::whereNull('driver_id')->where('journey_time' , '<' , $t1 )->where('journey_time','>',$t2)->orderBy('journey_time','asc')->get();
		$groups = Group::whereNull('driver_id')->orderBy('journey_time','asc')->get();
		
		foreach ($groups as $group)
		{
			$drivers = Driver::where('driver_status','=','vacant')->where('phone_status','=','alive')->get();
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
				self::send_push($people_so_far,11,array('driver_id'=>$closest_driver_id));
				self::driver_send_push(array($closest_driver_id),16,array('group_id'=>$group->group_id));
				
				/*
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
				}*/
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
	public function get_picture($driver_id)
	{
		$image_name = 'driver_'.$driver_id.'.png';
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		if (is_null($driver))
			return Response::download(public_path().'/images'.'/default-user.png',$image_name,
									array('content-type'=>'image/png') );
		$images = json_decode($driver->images);
		//$pathToFile = asset('images/');
		if (sizeof($images->profile_picture)==1)
			return Response::download(public_path().'/images'.'/default-user.png',$image_name,
									array('content-type'=>'image/png') );
		
		else
		{
			return Response::download(public_path().'/images'.'/'.$images->profile_picture
									,$images->profile_picture,
									array('content-type'=>'image/png') );
		}
		/*
		;
		$destinationPath = 'public/images/';
		$filename = 'favicon.ico';
		return URL::to('/').'/'.$destinationPath.$filename;*/
	}
	public function upload_picture($driver_id)
	{

		/*$requirements = ['photo'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);*/
		if (!Input::hasFile('photo'))
			return Error::make(1,32);
		$file = Input::file('photo');
		if (!$file->isValid())
		{
			return Error::make(1,31);
		}	
		$destinationPath = public_path()."/images/";
		$filename = md5('driver'.$driver_id).'.'.$file->getClientOriginalExtension();
		$uploadSuccess   = $file->move($destinationPath, $filename);
		$driver = Driver::where('driver_id','=',$driver_id)->first();
		$images = json_decode($driver->images);

		$images->profile_picture=$filename;
		try
		{
			Driver::where('driver_id','=',$driver_id)->update(array(
						'images'=>json_encode($images),
						));
		}
		catch(Exception $e) {
					return Error::make(101,101,$e->getMessage());
		}
		return Error::success('Photograph uploaded successfully!',array('driver_id'=>$driver_id));
	}
}
