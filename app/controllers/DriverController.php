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
			return Error::make(1,10);
		}
		try {
			Driver::where('driver_id','=',$driver_id)->update(array(
			'group_id' => Input::get('group_id'),
		));
			return Error::success("Group successfully added" , array("driver_id" => $driver_id));
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
		return $driver->group_id;
	}
}
