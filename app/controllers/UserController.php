<?php

class UserController extends BaseController {

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
	public function sendmail($user){
		try {
			Mail::queue('emails.verify', array('encryption'=>self::encrypt($user->email) , 'name'=>$user->first_name), function($message) use($user)
			{
			    $message->to(trim($user->email), trim($user->first_name))->subject('[Pickup] Please verify your email '.trim($user->email));
			});
		} catch (Exception $e) {
			print_r($e->getMessage());
		}
		
	}
	public function set_home()
	{
		$requirements = ['user_id','home_location','home_text'/*,'leaving_home'*/];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$user = User::where('id' , '=', Input::get('user_id'))->first();
		if (is_null($user))
			return Error::make(1,1);
		try {
			User::where('id','=',$user->id)->update(array(
				'home_location' => Input::get('home_location'),
				'home_text' => Input::get('home_text'),
			));
			self::compute_home_office($user->id);
			return Error::success("Home successfully Added" , array("user_id" => intval($user->id)));
			
		}
		 catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
		
	}
	public function set_office()
	{
		$requirements = ['user_id','office_location','office_text'/*,'leaving_office'*/];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$user = User::where('id' , '=', Input::get('user_id'))->first();
		if (is_null($user))
			return Error::make(1,1);
		try {
			User::where('id','=',$user->id)->update(array(
				'office_location' => Input::get('office_location'),
				'office_text' => Input::get('office_text'),
			));
			$state=self::compute_home_office($user->id);
			return Error::success("Office successfully Added" , array("user_id" => intval($user->id)));
			
		}
		 catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function compute_home_office($user_id)
	{
		$user = User::where('id' , '=', $user_id)->first();
		if (is_null($user))
			return Error::make(1,1);
		if (!(strcmp($user->home_location,"none")==0 || strcmp($user->office_location,"none")==0))
		{
			try 
			{
				$user->home_to_office=self::getPath($user->home_location,$user->office_location);
				$user->office_to_home=self::getPath($user->office_location,$user->home_location);
				$json_home_office=json_decode($user->home_to_office)->routes[0];
				$json_office_home=json_decode($user->office_to_home)->routes[0];
				$user->home_to_office=json_encode(Graining::get_hashed_grid_points(json_encode($json_home_office)));
				$user->office_to_home=json_encode(Graining::get_hashed_grid_points(json_encode($json_office_home)));
			}
			catch (Exception $e)
			{
				return Error::make(1,21);
			}
			$user->path_distance=$json_home_office->legs[0]->distance->value;
			$user->path_time=$json_home_office->legs[0]->duration->value;
			try {
			User::where('id','=',$user_id)->update(array(
				'home_to_office' => $user->home_to_office,
				'office_to_home' => $user->office_to_home,
				'path_distance' => $user->path_distance,
				'path_time' => $user->path_time,
			));
		}
		 catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
		}
	}
	public function add()
	{
		$requirements = ['fbid' ,'age','phone','company',
						 'name' , 'email' , 'gender' , 'device_id','mac_addr'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$user = User::where('fbid' , '=', Input::get('fbid'))->orWhere('email' , '=' , Input::get('email'))->first();
		if(!is_null($user)){
			$user->device_id = Input::get('device_id');
			$user->save();
			return Error::success("User Already Present" , array("user_id" => $user->id));
		}
		$user = new User;
		$user->fbid = Input::get('fbid');
		$user->age = Input::get('age');
		$user->phone = Input::get('phone');
		$user->company = Input::get('company');
		$user->first_name = Input::get('name');
		$user->second_name = "";//Input::get('second_name');
		$user->email =  Input::get('email');
		$user->gender = Input::get('gender');
		$user->device_id = Input::get('device_id');
		$user->registration_id = "";
		$user->mac_addr = Input::get('mac_addr');
		$user->current_pos="19.1336,72.9154";
		if (Input::has('company_email'))
			$user->company_email = Input::get('company_email');
		/*$user->home_location=;
		$user->home_text=Input::get('home_text');
		$user->office_location=Input::get('office_location');
		$user->office_text=Input::get('office_text');
		$user->leaving_office=Input::get('leaving_office');
		$user->leaving_home=Input::get('leaving_home');*/
		/*try {
		$user->home_to_office=self::getPath($user->home_location,$user->office_location);
		$user->office_to_home=self::getPath($user->office_location,$user->home_location);
		$json_home_office=json_decode($user->home_to_office)->routes[0];
		$json_office_home=json_decode($user->office_to_home)->routes[0];
		$user->home_to_office=json_encode(Graining::get_hashed_grid_points(json_encode($json_home_office)));
		$user->office_to_home=json_encode(Graining::get_hashed_grid_points(json_encode($json_office_home)));
		}
		catch (Exception $e)
		{
			return Error::make(1,21);
		}
		$user->path_distance=$json_home_office->legs[0]->distance->value;
		$user->path_time=$json_home_office->legs[0]->duration->value;*/
		try {
			$user->save();
			//$user->id = 10;
			//$this->sendmail($user);
			return Error::success("User successfully Added" , array("user_id" => $user->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function check_existence()
	{
		$requirements = ['fbid'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$user = User::where('fbid' , '=', Input::get('fbid'))->first();
		$final_data = array("user_present"=>0,"user_data"=>$user);
		if (!is_null($user))
		{
			$final_data['user_present']=1;
			$final_data['user_data']->home_to_office=NULL;
			$final_data['user_data']->office_to_home=NULL;
		}
		return Error::success("Successfully logged in",$final_data);
		//return $final_data;
		
	}
	public function getPath($start,$end)
	{
		$path=file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=$start&destination=$end");
		return $path;
	}
	public function gcm_add(){
		$requirements = ['reg_id' , 'user_id'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$user = User::find(Input::get('user_id'));
		if(is_null($user)){
			return Error::make(1,1);
		}
		$user->registration_id = Input::get('reg_id');
		$user->save();
		return Error::success("Registration ID successfully added");
	}
	public function periodic_route($user_id)
	{
		$requirements = ['position','event_ids'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$journey = Journey::where('id','=',$user_id)->orderBy('journey_time','desc')->get();
		if (is_null($journey))
			return Error::make(1,1);
		$journey = $journey[0];
		$positions = self::modify_location($user_id,Input::get('position'));
		$pending_events = self::get_pending_events($journey->journey_id,Input::get('event_ids'));
		return Error::success('periodic data',array('positions'=>$positions,
													'pending_events'=>$pending_events));
	}
	public function modify_location($user_id=0,$position)
	{
		$user = User::where('id','=',$user_id)->first();
		if(is_null($user)){
			return Error::make(1,1);
		}
		
		$new_coordinate_array = explode(',',$position);
		$timestamp=date('Y-m-d H:i:s', time());//Input::get('journey_time');
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*1);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*1);;

		$check_existing_journey = Journey::where('id' , '=' , intval($user_id))->
											where('journey_time' , '>' , $t2 )->
											where('journey_time' , '<' , $t1 )->
											where('group_id','!=',-1)->first();
		$final_data=array("driver"=>NULL,"mates"=>array());
		if (!is_null($check_existing_journey) && !is_null($check_existing_journey->group_id))
		{
			$group = Group::where('group_id','=',intval($check_existing_journey->group_id))->first();
			$people_so_far = json_decode($group->journey_ids);
			foreach ($people_so_far as $value) {
				if ($value==$check_existing_journey->journey_id)
					continue;
				$mate_journey = Journey::where('journey_id','=',$value)->first();
				$mate_user = User::where('id','=',$mate_journey->id)->first();
				array_push($final_data['mates'], array("user_id"=>intval($mate_user->id),
													"position"=>$mate_user->current_pos));
			}
			if (!is_null($group->driver_id))
			{
				$driver = Driver::where('driver_id','=',$group->driver_id)->first();
				if (is_null($driver))
					Error::make(1,19);
				$final_data['driver']=array("driver_id"=>intval($group->driver_id),
										  "position"=>$driver->current_pos);
			}
		}
		try {
			User::where('id','=',$user_id)->update(array(
				'current_pos' => Input::get('position'),
				));
			//$user->id = 10;
			//$this->sendmail($user);
			return $final_data;
			//return Error::success("User location changed" , array("positions" => $final_data));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function get($user_id=0)
	{
		$user = User::find($user_id);
		if(is_null($user)){
			return Error::make(1,1);
		}
		$user['error'] = 0;
		$user['message']="ok";
		return $user;
	}

	public function verify($code="")
	{
		$email = self::decrypt($code);
		$user = User::where('email','=',$email)->first();
		if(is_null($user))
		{
			return "Unkown Account";
		}
		return "Thanks , ".$user->first_name." <br> Your account has been successfully verified";
	}

	public function all_journey($uid)
	{
		$user = User::find($uid);
		if(is_null($user)){
			return Error::make(1,1);
		}
		$journey = Journey::where('id','=',$uid)->orderBy('updated_at','desc')->get();
		return $journey;
	}

	public function get_history($user_id)
	{
		$user = User::where('id' , '=', $user_id)->first();
		if (is_null($user))
			return Error::make(1,1);
		$all_journeys = Journey::where('id','=',$user_id)->get();
		$history = array();
		foreach ($all_journeys as $journey) {
			$group = Group::where('group_id','=',$journey->group_id)->first();
			if ($journey->group_id==-1)
			{
				//$journey_ids = json_decode($group->journey_ids);
				$data = array("mates"=>array(),"status"=>"cancelled",
					"start_text"=>$journey->start_text, "end_text"=>$journey->end_text,
					"distance"=>$journey->distance, "fare"=>0,
					"start_lat"=>$journey->start_lat,"start_long"=>$journey->start_long,
					"end_lat"=>$journey->end_lat,"end_long"=>$journey->end_long,
					"journey_time"=>$journey->journey_time);
				array_push($history,$data);
			}
			else if (!is_null($group) && strcmp($group->event_status,"completed")==0)
			{
				$journey_ids = json_decode($group->journey_ids);
				$mate_data = array();
				foreach($journey_ids as $mate_id)
				{
					if ($mate_id==$journey->journey_id)
						continue;
					$mate_pending = Journey::where('journey_id','=',$mate_id)->first();
					$mate_user = User::where('id','=',$mate_pending->id)->first();
					if (is_null($mate_user))
						return Error::make(1,1);
					array_push($mate_data, array('user_id'=>intval($mate_user->id),
												'fbid'=>$mate_user->fbid,
												'user_name'=>$mate_user->first_name,
												'age'=>$mate_user->age,
												'gender'=>$mate_user->gender));
				}
				$data = array("mates"=>$mate_data,"status"=>"completed",
					"start_text"=>$journey->start_text, "end_text"=>$journey->end_text,
					"distance"=>$journey->distance, "fare"=>1000,
					"start_lat"=>$journey->start_lat,"start_long"=>$journey->start_long,
					"end_lat"=>$journey->end_lat,"end_long"=>$journey->end_long,
					"journey_time"=>$journey->journey_time);
				array_push($history,$data);
			}
		}

		return Error::success("Here is the ride history!",array("history"=>$history));
	}
}
