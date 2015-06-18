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
	public function add()
	{
		$requirements = ['fbid' , 'name' , 'email' , 'gender' , 'device_id' , 'gcm_id','mac_addr' ,
		'home_location' , 'home_text' , 'office_location' , 'office_text' , 'leaving_office' , 
		'leaving_home'];
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
		$user->first_name = Input::get('name');
		$user->second_name = "";//Input::get('second_name');
		$user->email =  Input::get('email');
		$user->gender = Input::get('gender');
		$user->device_id = Input::get('device_id');
		$user->registration_id = Input::get('gcm_id');
		$user->mac_addr = Input::get('mac_addr');
		$user->current_pos="19.1336,72.9154";
		$user->home_location=Input::get('home_location');
		$user->home_text=Input::get('home_text');
		$user->office_location=Input::get('office_location');
		$user->office_text=Input::get('office_text');
		$user->leaving_office=Input::get('leaving_office');
		$user->leaving_home=Input::get('leaving_home');
		try {
		$user->home_to_office=self::getPath($user->home_location,$user->office_location);
		$user->office_to_home=self::getPath($user->office_location,$user->home_location);
		$json=json_decode($user->home_to_office);
		$user->home_to_office=json_encode(Graining::get_hashed_grid_points($user->home_to_office));
		$user->office_to_home=json_encode(Graining::get_hashed_grid_points($user->office_to_home));
		}
		catch (Exception $e)
		{
			return Error::make(1,21);
		}
		$user->path_distance=$json->routes[0]->legs[0]->distance->value;
		$user->path_time=$json->routes[0]->legs[0]->duration->value;
		try {
			$user->save();
			//$user->id = 10;
			//$this->sendmail($user);
			return Error::success("User successfully Added" , array("user_id" => $user->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
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
}
