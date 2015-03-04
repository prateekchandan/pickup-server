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
		Mail::send('emails.verify', array('encryption'=>self::encrypt($user->email) , 'user'=>$user), function($message) use($user)
		{
		    $message->to($user->email, $user->first_name)->subject('[Pickup] Please verify your email '.$user->email);
		});
	}
	public function add()
	{
		$requirements = ['fbid' , 'name' , 'email' , 'gender' , 'device_id' , 'gcm_id'];
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

		try {
			//$user->save();
			$user->id = 10;
			$this->sendmail($user);
			return;
			return Error::success("User successfully Added" , array("user_id" => $user->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
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
