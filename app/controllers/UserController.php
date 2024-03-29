<?php
/**
 * UserController.php
 *
 * Contains the UserController class.
*/


/**
 * UserController
 *
 * This class encompasses all functionality specific to users.
 * It inherits from the BaseController class and makes use of the functionality
 * provided there. Functions not in use have been assigned a deprecated tag.
 *
 * Any function here can return an error of three forms :-
 * 
 * required type :- When all HTTP request parameters aren't sent.
 *
 * error code :- Standard error messages with fixed codes. Codes in Error class.
 * 
 * other errors :- Other errors not handled thusfar. Need to be moved to type 2.
 *
 * @author Kalpesh Krishna <kalpeshk2011@gmail.com>
 * @copyright 2015 Pickup 
*/
class UserController extends BaseController {


	/**
	* Helper function to send emails to a given user. Currently not in use.
	* 
	* @deprecated
	* @param mixed[] $user The user object extracted from the database.
	* @return void
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


	/**
	* Helper function to get facebook ID via access token.
	*
	* Checks validity of access token as well. Returns 0 in 
	* the case of an invalid access token.
	* 
	* @param string $access_token A Facebook access token.
	* @return string Facebook ID.
	*/
	public function get_fbid($access_token) {
		// Sending request to graph API
		$fb_url = "https://graph.facebook.com/me?access_token=".$access_token;
		$opts = array(
		  'http' => array('ignore_errors' => true)
		);
		$context = stream_context_create($opts);
		$fb_data = json_decode(file_get_contents($fb_url, false, $context));
		
		// In case error encountered in Graph API
		if (isset($fb_data->error)) {
    		return 0;
		}

		// Getting fbid in the case of no error
		$fbid = $fb_data->id;
		return $fbid;
	}


	/**
	 * Function helps set home location for a given user via user_id.
	 *
	 * This function gets the user_id, home_location, home_text via the HTTP
	 * request and stores this data in the database. 
	 * Associated errors (error_code => description) :-
	 *
	 * 1 => Occurs when user_id isn't the id of any existing user.
	 * 
	 * Used by route :-
	 *
	 * Route::post('set_home','UserController@set_home');
	 *
	 * Parameters required by route :- <br>
	 * <b>user_id</b> <i>int</i> ID of user whose home we wish to set.<br>
	 * <b>home_location</b> <i>string</i> Comma separated location of the form "latitude,longitude".
	 * Example :- 19,72.<br>
	 * <b>home_text</b> <i>string</i> The name of the place/locality. 
	 * @return mixed[] return type of Error::success() on successful execution.
	*/
	public function set_home()
	{
		$requirements = ['user_id','home_location','home_text'];
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


	/**
	 * Function helps set office location for a given user via user_id.
	 *
	 * This function gets the user_id, office_location, office_text via the HTTP
	 * request and stores this data in the database. 
	 * Associated errors (error_code => description) :-
	 *
	 * 1 => Occurs when user_id isn't the id of any existing user.
	 * 
	 * Used by route :-
	 *
	 * Route::post('set_office','UserController@set_office');
	 * 
	 * @return mixed[] return type of Error::success() on successful execution.
	*/
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


	/**
	 * Helper function used to compute and store hashed path points.
	 *
	 * This function is used by set_home() and set_office(). Those functions 
	 * check whether both home and office have been set. If this is the case,
	 * compute_home_office() uses Google's Directions API and Graining.php
	 * to obtain the hashed path between home and office and vice versa.
	 * Path distance and time are also stored.
	 *
	 * @param int $user_id The ID of the user for whom we wish to compute the paths.
	 * @return void
	*/
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


	/**
	 * Primary function to add new users into the user database.
	 *
	 * Before any API can be used, we have to add a user and obtain his/her user_id.<br>
	 * Used by route :- <br>
	 * Route::post('add_user', array('as' => 'user.add', 'uses' => 'UserController@add'));
	 *
	 * Parameters required by route :- <br>
	 * <b>name</b> <i>string</i> - Name of user to be added.<br>
	 * <b>age</b> <i>string</i> - age of user to be added.<br>
	 * <b>access_token</b> <i>string</i> - Facebook access token of user.<br>
	 * <b>phone</b> <i>string</i> - Phone Number of the user. <br>
	 * <b>email</b> <i>string</i> - email ID of user.<br>
	 * <b>gender</b> <i>string</i> - Male or Female type of user<br>
	 * <b>company</b> <i>string</i> - Company of user<br>
	 * <b>device_id</b> <i>string</i> - Android phone device ID<br>
	 * <b>mac_addr</b> <i>string</i> - MAC address of NIC used by phone<br>
	 * <b>company_email</b> <i>string</i> - (optional parameter) Company offical email ID
	 * <b>platform</b> <i>string</i> - (optional parameter) Platform on which product is run
	 *
	 * @return mixed[] This array contains the user_id of the user.
	*/
	public function add()
	{
		$requirements = ['access_token' ,'age','phone','company','name' , 
						'email' , 'gender' , 'device_id','mac_addr'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		// Getting Facebook ID using access token.
		$fbid = self::get_fbid(Input::get('access_token'));
		// Check whether non empty access token is valid.
		if (Input::get('access_token')!="0" && $fbid==0)
			return Error::make(1,45);

		// Checking whether user exists using his email/facebook ID.
		// This indicates a login via 
		$editUser = False;
		$user = User::where('fbid' , '=', $fbid)->
					  orWhere('email' , '=' , Input::get('email'))->
					  first();
		if (!is_null($user)) {
			// User exists and has logged in with Facebook.
			$editUser = True;
		}

		// Case where user has registered via only Phone
		$user = User::where('phone','=',Input::get('phone'))->first();
		if (!is_null($user) && $user->fbid=="")
			$editUser = True;

		// Adding new user if old user is not found.
		if (!$editUser)
			$user = new User;
		if ($fbid!=0) // Needed to prevent erasing FB id for debugging
			$user->fbid = $fbid;
		$user->age = Input::get('age');
		$user->phone = Input::get('phone');
		$user->company = Input::get('company');
		$user->first_name = Input::get('name');
		$user->second_name = "";
		$user->email =  Input::get('email');
		$user->gender = Input::get('gender');
		$user->device_id = Input::get('device_id');
		$user->registration_id = ""; //Comes under gcm_add()
		$user->mac_addr = Input::get('mac_addr');
		$user->current_pos="19.1336,72.9154"; // Updated in the periodic route.
		if (Input::has('company_email'))
			$user->company_email = Input::get('company_email');
		if (Input::has('platform'))
			$user->platform = Input::get('platform');

		// Saving user object created.
		try {
			$user->save();
			if ($editUser)
				return Error::success("User Already Present" , array("user_id" => $user->id));
			else
				return Error::success("User successfully Added" , array("user_id" => $user->id));
		} 
		catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}


	/**
	 * Function to test user's existence on database solely based on Facebook access
	 * token.
	 *
	 * The HTTP request contains the facebook access token. The function is run every 
	 * time the app is reinstalled. It returns the user data if he had registered earlier.
	 * 
	 * Used by route :- <br>
	 * Route::get('user_exists','UserController@check_existence');
	 * 
	 * Parameters required by route :- <br>
	 * <b>access_token</b> <i>string</i> - Facebook access token of user whose existence 
	 * we wish to check.
	 *
	 * @return mixed[] Returns user data if user is present. Else returns NULL in that
	 * field.
	*/
	public function check_existence()
	{
		$requirements = ['access_token'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		// Getting Facebook ID using access token.
		$fbid = self::get_fbid(Input::get('access_token'));
		if ($fbid==0)
			return Error::make(1,45);

		$user = User::where('fbid' , '=', $fbid)->first();

		$final_data = array("user_present"=>0,"user_data"=>$user);
		if (!is_null($user))
		{
			$final_data['user_present']=1;
			$final_data['user_data']->home_to_office=NULL;
			$final_data['user_data']->office_to_home=NULL;
		}
		return Error::success("Successfully logged in",$final_data);
	}


	/**
	 * Helper function to get path between two points.
	 *
	 * Used by all functions of this class. Returns Google Maps path
	 * by contacting Google Direction API.
	 * Inputs must be comma separated (latitude,longitude) type points.
	 *
	 * @param string $start comma separated latitude,longitude string
	 * @param string $end comma separated latitude,longitude string
	 * @return mixed[] raw path returned by Directions API
	*/
	public function getPath($start,$end)
	{
		$path=file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=$start&destination=$end");
		return $path;
	}


	/**
	 * Function to add GCM ID for users.
	 *
	 * This function takes the GCM registration ID and a user_id as input
	 * and stores this data in the database. registration_id is used to send
	 * push notifications.
	 * 
	 * Used by route :- <br>
	 * Route::post('register_gcm', array('as' => 'user.gcm', 'uses' => 'UserController@gcm_add'));
	 * 
	 * Parameters required by route :- <br>
	 * <b>reg_id</b> <i>string</i> - GCM ID of user whose existence we wish to check.
	 * <b>user_id</b> <i>int</i> - User ID of user whose details we wish to add.
	 *
	 * @return mixed[]
	*/
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


	/**
	 * The route called every 30 seconds on the Android app.
	 *
	 * This route is used to ACK the push notifications, 
	 * flush the un-acked pending push notifications and 
	 * modify the user location when an update is received.
	 *
	 * Route::post('periodic_route/{id}','UserController@periodic_route');
	 *
	 * Parameters required by route :- <br>
	 * <b>position</b> <i>string</i> - Comma separated location of the user.
	 * <b>event_ids</b> <i>int[]</i> - ACK array of IDs of push notifications received.
	 *
	 * @param int $user_id User ID of user.
	 * @return mixed[] User object.
	 */	
	public function periodic_route($user_id)
	{
		$requirements = ['position','event_ids'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		
		// Aim is to get latest journey of the user.
		// TODO :- Add checks to see journey is active or not.
		$journey = Journey::where('id','=',$user_id)->
							orderBy('journey_time','desc')->
							get();
		// Can't run periodic route if no journey exists.
		if (is_null($journey) || sizeof($journey)==0)
			return Error::make(1,1);

		$journey = $journey[0];

		// Doucmented below. Modifies location in database.
		$positions = self::modify_location($user_id,Input::get('position'));

		// documented in BaseController.php. Gets pending events of latest journey ID.
		$pending_events = self::get_pending_events($journey->journey_id,Input::get('event_ids'));

		return Error::success('periodic data',array('positions'=>$positions,
													'pending_events'=>$pending_events));
	}

	/**
	 * Helper function to modify location, used by periodic route.
	 *
	 * This route is used to modify the location of the given user.
	 * TODO :- Work on this route extensively.
	 *
	 * @param int $user_id User ID of user.
	 * @param string $position Comma separated location of user.
	 * @return mixed[] User object.
	 */
	public function modify_location($user_id=0,$position)
	{
		$user = User::where('id','=',$user_id)->first();
		if(is_null($user)){
			return Error::make(1,1);
		}
		
		// explode() used due to input format
		$new_coordinate_array = explode(',',$position);

		// Creating a window of +/- 1 hour to see active journey.
		// TODO :- Shift this to periodic route. (proposed)
		$timestamp = date('Y-m-d H:i:s', time());
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*1);
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*1);

		// journey_time in window, journey is not cancelled or completed. 
		// TODO :- Robustness needed for journey object states.
		// -1, NULL problem. Switch to status field.
		$check_existing_journey = Journey::where('id' , '=' , intval($user_id))->
										   where('journey_time' , '>' , $t2 )->
										   where('journey_time' , '<' , $t1 )->
										   where('group_id','!=',-1)->
										   first();

		$final_data=array("driver"=>NULL,"mates"=>array());

		// Return appropriate location data only when there is an existing journey with people on it.
		if (!is_null($check_existing_journey) && !is_null($check_existing_journey->group_id))
		{
			$group = Group::where('group_id','=',intval($check_existing_journey->group_id))->first();
			
			$people_so_far = json_decode($group->journey_ids);
			foreach ($people_so_far as $value) {

				// Ignore case where passenger is user who's history we want.
				if ($value==$check_existing_journey->journey_id)
					continue;

				// Getting location of user.
				$mate_journey = Journey::where('journey_id','=',$value)->first();
				$mate_user = User::where('id','=',$mate_journey->id)->first();
				array_push($final_data['mates'], array("user_id"=>intval($mate_user->id),
													"position"=>$mate_user->current_pos));
			}

			// Run if driver has been allocated.
			if (!is_null($group->driver_id))
			{
				$driver = Driver::where('driver_id','=',$group->driver_id)->first();
				// Safety check. Should never happen.
				if (is_null($driver))
					return Error::make(1,19);
				$final_data['driver']=array("driver_id"=>intval($group->driver_id),
										    "position"=>$driver->current_pos);
			}
		}
		try {
			User::where('id','=',$user_id)->update(array(

						'current_pos' => Input::get('position'),

						));
			return $final_data;
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	/**
	 * Used to get user details.
	 *
	 * Route::get('user/{user_id}', array('as' => 'user.add',
	 * 'uses' => 'UserController@get'));
	 *
	 * @param int $user_id User ID of user.
	 * @return mixed[] User object.
	 */
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

	/**
	 * Used to verify the user's account via email/phone.
	 *
	 * @param string $code The verification code.
	 * @return string Verification message 
	 * @deprecated 
	 */
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


	/**
	 * Return a list of all journeys registered by a given user in descending order.
	 * 
	 * Used by route :- <br>
	 * Route::get('user/{user_id}/all_journey', array('as' => 'user.journey',
	 * 'uses' => 'UserController@all_journey'));
	 *
	 * @param int $user_id User ID of person in consideration.
	 * @return object[] List of journeys travelled by user.
	 * @deprecated
	 */
	public function all_journey($user_id)
	{
		$user = User::find($user_id);
		if(is_null($user)){
			return Error::make(1,1);
		}
		$journeys = Journey::where('id','=',$user_id)->orderBy('updated_at','desc')->get();
		return $journeys;
	}


	/**
	 * Return a detailed history of all journeys cancelled and all journeys completed.
	 * 
	 * Does not return current journeys. The status field tells us the whether the journey
	 * was completed or cancelled.
	 *
	 * Used by route :- <br>
	 * Route::get('get_history/{id}','UserController@get_history');
	 *
	 * @param int $user_id User ID of person in consideration.
	 * @return object[] History of user.
	 */
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
				// Case where journey was cancelled.
				$data = array("mates"=>array(),
							  "status"=>"cancelled",
							  "start_text"=>$journey->start_text,
							  "end_text"=>$journey->end_text,
							  "distance"=>$journey->distance, 
							  "fare"=>0,
							  "start_lat"=>$journey->start_lat,
							  "start_long"=>$journey->start_long,
							  "end_lat"=>$journey->end_lat,
							  "end_long"=>$journey->end_long,
							  "journey_time"=>$journey->journey_time);

				array_push($history,$data);
			}
			else if (!is_null($group) && strcmp($group->event_status,"completed")==0)
			{
				// Case where journey was completed.
				$journey_ids = json_decode($group->journey_ids);
				$mate_data = array();
				// Going through all passengers in that group.
				foreach($journey_ids as $mate_id)
				{
					// Ignore case where passenger is user who's history we want.
					if ($mate_id==$journey->journey_id)
						continue;
					$mate_pending = Journey::where('journey_id','=',$mate_id)->first();
					$mate_user = User::where('id','=',$mate_pending->id)->first();

					// Safety check, should never happen.
					if (is_null($mate_user))
						return Error::make(1,1);

					// Final mate data.
					array_push($mate_data, array('user_id'=>intval($mate_user->id),
												'fbid'=>$mate_user->fbid,
												'user_name'=>$mate_user->first_name,
												'age'=>$mate_user->age,
												'gender'=>$mate_user->gender));
				}
				$data = array("mates"=>$mate_data,
							  "status"=>"completed",
							  "start_text"=>$journey->start_text, 
							  "end_text"=>$journey->end_text,
							  "distance"=>$journey->distance, 
							  "fare"=>1000,
							  "start_lat"=>$journey->start_lat,
							  "start_long"=>$journey->start_long,
							  "end_lat"=>$journey->end_lat,
							  "end_long"=>$journey->end_long,
							  "journey_time"=>$journey->journey_time);

				array_push($history,$data);
			}
		}
		return Error::success("Here is the ride history!",array("history"=>$history));
	}
}
