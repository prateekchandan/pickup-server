<?php
/**
 * HomeController.php
 *
 * Contains the HomeController class.
*/

/**
 * Helper function for usort of paths. 
 *
 * Used in the find_path() function for sorting paths. 
 * Utilizes distance as a measure for comparison.
 *
 * @param mixed[] $a The first path
 * @param mixed[] $b The second path
 * @return boolean The value obtained on comparing $a with $b.
 */
function cmp($a,$b) {
	$d1 = 0;
	$d2 = 0;
	foreach ($a->legs as $key => $legobj) {
		$d1 += $legobj->distance->value;
	}
	foreach ($b->legs as $key => $legobj) {
		$d2 += $legobj->distance->value;
	}
	return $d1 < $d2;
}


/**
 * HomeController
 *
 * This class encompasses all functionality specific to journeys.
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
class HomeController extends BaseController {


	public $debug = 0;


	/**
	 * A helper function to find the point to point distance.
	 *
	 * This function approximately calculates the point to point distance
	 * between two points on the surface of the Earth. Care has been taken
	 * to take into account the curvature of the earth and other spherical
	 * factors.
	 *
	 * Convinient and fast function for quick distance estimates. Should be 
	 * switched to when time complexity is a constraint. Google calls for 
	 * exact road distances are expensive.
	 * 
	 * @param float $lat1 Latitude of point 1
	 * @param float $lon1 Longitude of point 1
	 * @param float $lat2 Latitude of point 2
	 * @param float $lon2 Longitude of point 2
	 * @param string $unit Unit for measurement. Default is Kilometre.
	 * Nautical miles("N") and miles("M") are viable options. Invalid input
	 * returns output in miles.
	 * @return float Distance computed. 
	 */
	public function distance($lat1, $lon1, $lat2, $lon2, $unit = "K") {

		$theta = $lon1 - $lon2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  
		cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;

		$unit = strtoupper($unit);

		if ($unit == "K") { // Kilometres
			return ($miles * 1.609344);
		} else if ($unit == "N") { // Nautical miles
			return ($miles * 0.8684);
		} else { // Miles
			return $miles;
		}
	}


	/**
	 * A helper function to find the country, state, city and locality of
	 * a point using Google Geocoding API.
	 *
	 * This function utilizes the Gecoding API to determine the address
	 * elements of the given location. Care has been taken to fill up
	 * appropriate fields logically when the API fails to provide that data.
	 * 
	 * TODO :- Use this function for "NOT IN MUMBAI" error message.
	 *
	 * @param float $lat1 Latitude of point
	 * @param float $lon1 Longitude of point
	 * @return mixed[] associative array with address information. 
	 */
	public function get_address($latitude = 19.12 , $longitude = 72.91)
	{

		try {
			$address = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude"));
		} catch (Exception $e) {
			return 0;
		}

		// JSON parsing result
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


		if($city == "" && $locality!="") {
			$city = $locality;
			$locality = $subl;
		}

		if($subl != "") {
			$locality = $subl;
		}

		return array('country'=>$country, 
					 'city' => $city, 
					 'state' => $state, 
					 'locality'=>$locality);
	}


	/**
	 * A helper function to find actual road path between two points.
	 *
	 * This functions is used for all calls to the Google Directions API.
	 * This function operates on an additional flag variable which can be
	 * 0 or 1.  1 returns complete google path. Flag 0 returns google route 
	 * with paths ordered by distance.
	 * 
	 * @param float $lat1 Latitude of point 1
	 * @param float $lon1 Longitude of point 1
	 * @param float $lat2 Latitude of point 2
	 * @param float $lon2 Longitude of point 2
	 * @param float[2][] $waypoints Array of lat/long points acting as 
	 * intermediate points in the journey.
	 * @param int $flag Can be 1 or 0. 
	 * @return mixed[] Final google determined path.
	 */
	public function find_path($lat1 = 0, $log1 = 0, $lat2 = 0, $log2 = 0,
							  $waypoints=array(), $flag=0) 
	{
		$address = "https://maps.googleapis.com/maps/api/directions/json?origin=$lat1,$log1&destination=$lat2,$log2&waypoints=";
		// Adding waypoints.
		foreach ($waypoints as $key => $value) {
			$address .= $value[0].','.$value[1].'|';
		}
		$address .= "&alternatives=true&sensor=false";
		
		// Try contacting Google server.
		try {
			$address = json_decode(file_get_contents($address));
		} catch (Exception $e) {
			return 0;
		}

		// Sorting paths by distance using global function cmp.
		$path  = $address->routes;
		usort($path,'cmp');

		// If invalid input in sent to Google server.
		if(sizeof($path)==0)
			return 0;

		if ($flag==1)
			return $address;
		else
			return $path;
	}


	/**
	 * Fundamental route to make a journey request and provide details.
	 *
	 * This function is implemented under the route :-
	 * <br>
	 * Route::post('add_journey', array('as' => 'journey.add',
	 * 'uses' => 'HomeController@journey_add'));
	 * 
	 * Parameters required by route :- <br>
	 * <b>user_id</b> <i>int</i> - User ID of person registering journey.<br>
	 * <b>margin_after</b> <i>string</i> - Period user is ready to wait for.<br>
	 *
	 * <b>alternate_journey_time</b> <i>string</i> - (optional parameter)
	 * Used to send future journey_time for website bypass.<br>
	 *
	 * <b>start_lat</b> <i>float</i> - Latitude of start point. <br>
	 * <b>start_long</b> <i>float</i> - Longitude of start point. <br>
	 * <b>end_lat</b> <i>float</i> - Latitude of end point. <br>
	 * <b>end_long</b> <i>float</i> - Longitude of end point. <br>
	 * <b>margin_before</b> <i>string</i> - deprecated, TODO :- remove. <br>
	 * <b>preference</b> <i>string</i> - Choice of car. Between 1 and 5.<br>
	 * <b>start_text</b> <i>string</i> - Name of starting point. <br>
	 * <b>end_text</b> <i>string</i> - Name of ending point. <br>
	 * 
	 * @return mixed[] Error::success type Response with journey_id and
	 * journey_time
	 */
	public function journey_add() 
	{
		// An initial check for user_id and margin_after.
		$requirements = ['user_id','margin_after'];
		$check  = self::check_requirements($requirements);
		if($check)
		return Error::make(0,100,$check);

		// Used to set journey in future for web bypass. Else use time()
		if(Input::has('alternate_journey_time')) {
			$timestamp=date('Y-m-d H:i:s',strtotime(Input::get('alternate_journey_time'))+intval(Input::get('margin_after'))*60);
		}
		else
			$timestamp=date('Y-m-d H:i:s', time()+intval(Input::get('margin_after'))*60);
		
		// Creating window of +/- one hour.
		// TODO :- Extend $t2 to infinity for website registrations?
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*1);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*1);;

		// Existing journeys in time window and group_id != -1
		// group_id = -1 corresponds to cancelled journey requests.
		// TODO :- migrate request states to strings. -1, NULL, ID isn't robust.
		$check_existing_journey =  Journey::where('id' , '=' , intval(Input::get('user_id')))->
											where('journey_time' , '>' , $t2 )->
											where('journey_time' , '<' , $t1 )->
											where(function($query)
            								{
                								$query->
                								whereNull('group_id')->
                								orWhere('group_id', '!=', -1);
            								})->
            								first();

        // Un-cancelled journey request in time-window exists,
		if (!is_null($check_existing_journey))
		{
			// Check whether user wishes to edit exisiting or create new.
			$editIntention=False;
			if (is_null($check_existing_journey->group_id))
				$editIntention=True;

			// Redirect to edit_journey route.
			if ($editIntention==True)
				return $this->journey_edit($check_existing_journey->journey_id);
			else
			{
				// Case where old journey is cancelled.
				if (intval($check_existing_journey->group_id)!=-1)
				{
					$response = self::cancel_journey(intval($check_existing_journey->journey_id));
					// In case cancelling journey encountered an error.
					// Possible when he is in the car and app restarts.
					if ($response->original['error']==1)
						return $response;
				}
			}
		}

		$requirements = ['start_lat' , 'start_long', 'end_lat', 
						 'end_long' , 'user_id' , 'margin_after' ,
						 'margin_before' , 'preference' , 
						 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
		return Error::make(0,100,$check);

		// Getting Google Directions API path.
		$path = $this->find_path(Input::get('start_lat'), 
								 Input::get('start_long'), 
								 Input::get('end_lat'), 
								 Input::get('end_long'), array(), 1);
		// Path not found.
		if(is_null($path) || (is_int($path) && $path==0)) {
			return Error::make(0,3);
		}

		$path1=NULL;
		$path2=NULL;
		$path3=NULL;
		// TODO :- if condition not needed now.
		if (array_key_exists(0, $path->routes))
		{
			$path1 = $path->routes[0];	
			$start_location = self::get_address(Input::get('start_lat'),
												Input::get('start_long'));
			$end_location = self::get_address(Input::get('end_lat'),
											  Input::get('end_long'));
			
			if ($start_location==0 || $end_location==0)
				return Error::make(1,36);
			if (strpos($start_location['city'],'Mumbai') === false && 
				strpos($start_location['city'],'Thane') === false) {
    			return Error::make(1,36);
			}
			if (strpos($end_location['city'],'Mumbai') === false && 
				strpos($end_location['city'],'Thane') === false) {
    			return Error::make(1,36);
			}
		}
		else
			return Error::make(1,23);

		// Storing path2 if exists
		if (array_key_exists(1, $path->routes))
			$path2 = $path->routes[1];

		// Storing path3 if exists
		if (array_key_exists(2, $path->routes))
			$path3 = $path->routes[2];

		// Google path distance
		$distance  = 0;
		foreach ($path1->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time = 0;

		// Time estimate by Google
		$path_time = 0;
		foreach ($path1->legs as $key => $value) {
			$path_time += $value->duration->value;
		}

		// Journey > 100 km
		if($distance > 100000)
			return Error::make(1,4);

		// Valid user
		$user = User::find(Input::get('user_id'));
		if(is_null($user))
		return Error::make(1,1);

		// Valid margin_after and margin_before
		if(!(is_numeric(Input::get('margin_after')) && is_numeric(Input::get('margin_before'))))
		return Error::make(1,7);
		if(Input::get('margin_after') > 60 || Input::get('margin_before') >60 || 
		   Input::get('margin_after') < 0 || Input::get('margin_before') < 0)
		return Error::make(1,7);

		// Valid preference
		if(!is_numeric(Input::get('preference')) || Input::get('preference') > 5 || 
		   Input::get('preference') < 1)
		return Error::make(1,8);

		// Filling up database entry.
		$journey = new Journey;

		$journey->start_lat = Input::get('start_lat');
		$journey->start_long = Input::get('start_long');
		$journey->end_lat = Input::get('end_lat');
		$journey->end_long = Input::get('end_long');
		
		// Store path after appropriate Graining
		$journey->path = json_encode(Graining::get_hashed_grid_points(json_encode($path1)));
		if (!is_null($path2))
		$journey->path2 = json_encode(Graining::get_hashed_grid_points(json_encode($path2)));
		if (!is_null($path3))
		$journey->path3 = json_encode(Graining::get_hashed_grid_points(json_encode($path3)));
		
		$journey->start_text = Input::get('start_text');
		$journey->end_text = Input::get('end_text');
		$journey->id = Input::get('user_id');
		$journey->journey_time = $timestamp;
		$journey->margin_before = Input::get('margin_before');
		$journey->margin_after = Input::get('margin_after');
		$journey->preference = Input::get('preference');
		$journey->distance = $distance;
		$journey->time = $path_time;

		// SQL insert query
		try {
			$journey->save();
			return Error::success("Journey successfully Registered",
								 array('journey_id'=>$journey->id,
									   'journey_time'=>$timestamp));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}


	/**
	 * Route combining functionality of journey_add() and 
	 * get_best_match()
	 *
	 * This function is implemented under the route :-
	 * <br>
	 * Route::post('journey_request','HomeController@journey_request');
	 * 
	 * Parameters required by route :- <br>
	 * <b>user_id</b> <i>int</i> - User ID of person registering journey.<br>
	 * <b>margin_after</b> <i>string</i> - Period user is ready to wait for.<br>
	 * <b>start_lat</b> <i>float</i> - Latitude of start point. <br>
	 * <b>start_long</b> <i>float</i> - Longitude of start point. <br>
	 * <b>end_lat</b> <i>float</i> - Latitude of end point. <br>
	 * <b>end_long</b> <i>float</i> - Longitude of end point. <br>
	 * <b>margin_before</b> <i>string</i> - deprecated, TODO :- remove. <br>
	 * <b>preference</b> <i>string</i> - Choice of car. Between 1 and 5.<br>
	 * <b>start_text</b> <i>string</i> - Name of starting point. <br>
	 * <b>end_text</b> <i>string</i> - Name of ending point. <br>
	 * 
	 * @return mixed[] Error::success type Response with journey_id and
	 * journey_time
	 */
	public function journey_request() {

		// TODO :- $requirements not required here.
		$requirements = ['user_id', 'margin_after', 'start_lat', 'start_long',
						 'end_lat', 'end_long', 'user_id', 'margin_after',
						 'margin_before', 'preference', 'start_text', 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
		return Error::make(0,100,$check);

		// Running journey_add()
		$add_journey = self::journey_add();

		if ($add_journey->original['error']==0)
		{
			// Error free journey_add()
			$journey_id=$add_journey->original['journey_id'];
			$best_match = self::get_best_match($journey_id);
			if ($best_match->original['error']==0)
			{
				// Error free get_best_match()
				
				$fare = 0; 
				$distance = 0;
				try {
					$estimates = CostCalc::fare_estimate($journey_id);
					$fare = $estimates["fare"];	
					$distance = $estimates["distance"];
				}
				catch(Exception $e) {
					// Delete journey object created to rollback transaction.
					Journey::where('journey_id','=',$journey_id)->delete();
					return Error::make(101,101,$e->getMessage());
				}
				// Sending estimated data
				// TODO :- estimate driver_reach_time properly
				// Use -1 if no drivers found
				// Error::success("Journey registered, No drivers available",$data)
				$data=array("journey_id"=>$journey_id,
							"best_match"=>$best_match->original['best_match'],
							"match_amount"=>$best_match->original['match_amount'],
							"estimated_driver_reach_time"=>intval(Input::get('margin_after')),
							"estimated_fare"=>$fare,
							"distance"=>$distance,
							);
				return Error::success("Journey registered",$data);
			}
			else
			{
				// Delete journey object created to rollback transaction.
				Journey::where('journey_id','=',$journey_id)->delete();
				return $best_match;
			}
		}
		else
			return $add_journey;
	}


	/**
	 * Obtains most suitable group for a given journey ID.
	 *
	 * Matches all existing groups not in ride with the provided 
	 * journey id. The best match is chosen and stored in the Journey 
	 * database for future usage.
	 *
	 * Implemented in the following route :- <br>
	 * Route::get('get_best_match/{id}','HomeController@get_best_match');
	 *
	 * @param int $journey_id The Journey ID whose best match we want.
	 * @return mixed[] Data about the best match
	 */
	public function get_best_match($journey_id)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		$new_user = User::where('id','=',$journey->id)->first();

		$best_match=NULL;
		$best_match_value=0;
		$path_number=1;
		$ideal_match_found=False;
		// Match with groups with two or more people.
		// for loop needed to scan all 3 paths of $journey_id
		for ($i=1;$i<=3;$i++)
		{
			$match_array = self::find_mates($journey_id,$i,1,False)['mates'];
			
			// Storing logs of matching algorithm
			self::log_matches("Matching route ".$i." of journey_id ".$journey_id." with groups...\n");
			$log_data=print_r($match_array,true);
			self::log_matches($log_data);

			// Top match
			$match = $match_array[0];
			
			// Top match exists and is better than current best match.
			if (!is_null($match) && $match->match_amount>$best_match_value)
			{
				$best_match=$match;
				$best_match_value=$match->match_amount;
				$path_number=$i;
			}
		}

		if (!is_null($best_match))
		{
			$ideal_match_found=True;
			// Swap path 1 value with path i to help add_to_group() 
			self::swap_paths($journey_id,1,$path_number);
		}

		// Idea is to fill up groups with two or more people first.
		// If no such group is found, try groups with one person.
		// Stronger matching with all 3 paths of that one person considered
		// as well.
		// Only executed if no match found till now.
		if ($ideal_match_found==False)
		{
			for ($i=1;$i<=3;$i++)
			{
				// For all three paths of person requesting for journey.
				for ($j=1;$j<=3;$j++)
				{
					// For all three paths of lonely person in group.
					$match_array = self::find_mates($journey_id,$i,$j,True)['mates'];
					
					// Storing logs of matching algorithm 
					self::log_matches("Matching route ".$i." of journey_id ".$journey_id." with lonely group journey ".$j."...\n");
					$log_data=print_r($match_array,true);
					self::log_matches($log_data);
					
					// Top match
					$match = $match_array[0];
					
					// Top match exists and is better than current best match. 
					if (!is_null($match) && $match->match_amount>$best_match_value)
					{
						$best_match=$match;
						$best_match_value=$match->match_amount;
						$path_number=$i;
					}
				}
			}
			if (!is_null($best_match))
			{
				$ideal_match_found=True;
				// Swap path 1 value with path i to help add_to_group() 
				self::swap_paths($journey_id,1,$path_number);
			}
		}
		if (!is_null($best_match))
		{
			// Match found. :)
			$id = $best_match->group_id;
			$best_match->id=intval($id);
			try {
				Journey::where('journey_id','=',$journey_id)->update(array(
					'best_match' => json_encode($best_match),
				));
			}
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}


		}
		else
		{
			// No match found. :(
			try {
				Journey::where('journey_id','=',$journey_id)->update(array(
					'best_match' => NULL,
				));
			}
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}

		// Finalizing data to send.
		$msg="Mates found!";
		if (is_null($best_match))
		{
			$best_match=json_decode ("{}");
			$msg="No Mates found!";
		}

		$final_data = array("match_amount"=>$best_match_value,"best_match"=>$best_match);
		return Error::success($msg,$final_data);
	}


	/**
	 * A helper function to get the group details of a particular group.
	 *
	 * This function returns details of a particular group. Path details
	 * are set to NULL as they are big and unnecessary.
	 * 
	 * Implemented under :-<br>
	 * Route::get('get_group/{id}' , array('as' => 'group.get',
	 * 'uses' => 'HomeController@get_group'));
	 *
	 * @param int $group_id The ID whose group we wish for. 
	 * @return mixed[] Group details array.
	 */
	public function get_group($group_id=0)
	{
		$group = Group::where('group_id','=',$group_id)->first();
		if(is_null($group)){
			return Error::make(1,17);
		}
		// Don't send unecessary Graining data
		$group->path=null;
		return Error::success("Group Details..",array('group'=>$group));
	}


	/**
	 * A helper function to generate the new path for people 
	 * travelling together.
	 *
	 * This function does not decide the order of waypoints. That is 
	 * taken care by getwaypoints(). This function uses the data returned
	 * by getwaypoints() and executes a Google Directions call to get
	 * the path data.
	 *
	 * @param int $group_id The ID whose path we want.
	 */
	public function generate_group_path($group_id)
	{
		$group = Group::where('group_id','=',$group_id)->first();
		if (is_null($group))
			return;

		// Order of waypoints.
		$final_path=json_decode($group->path_waypoints);
		
		// All waypoints except the start and the end.
		$waypoints=array();
		for ($j=1;$j<sizeof($final_path->startwaypoints);$j++)
		{
			array_push($waypoints,$final_path->startwaypoints[$j]);
		}
		for ($j=0;$j<sizeof($final_path->endwaypoints)-1;$j++)
		{
			array_push($waypoints,$final_path->endwaypoints[$j]);
		}

		// Call to helper function to contact Google Directions API.
		$path=self::find_path($final_path->startwaypoints[0][0],
							  $final_path->startwaypoints[0][1],
							  end($final_path->endwaypoints)[0],
							  end($final_path->endwaypoints)[1],
							  $waypoints,1)->routes[0];
		
		// Hashing path obtained.
		$hashed_path = json_encode(Graining::get_hashed_grid_points(json_encode($path)));
		
		// Database entry.
		Group::where('group_id','=',$group_id)->update(array(
			'path' => $hashed_path,
		));
	}


	/**
	 * Route to add a given user into a particular group.
	 *
	 * This function acts as a route. It is used to confirm Journey objects
	 * by adding them to a suitable group as computed by get_best_match()
	 * For best results, use this route after get_best_match() only.
	 * New groups are also created via this function.
	 * 
	 * Implemented in the route :- <br>
	 * Route::get('confirm/{id}','HomeController@add_to_group');
	 *
	 * @param int $journey_id The ID whose Journey we wish for. 
	 * @return mixed[] Response object with group id of newly created group.
	 */
	public function add_to_group($journey_id)
	{
		// Check validity of journey ID.
		$journey_id=intval($journey_id);
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}

		// In the case user is already registered into a group,
		// Send person running route people in current group.
		if (!is_null($journey->group_id)) {
			$send_group=Group::where('group_id','=',$journey->group_id)->first();
			// TODO :- if not needed here.
			if(!is_null($send_group)) {
				$mates=array();
				foreach (json_decode($send_group->journey_ids) as $mate_id) {
					if ($mate_id==$journey_id)
						continue;
					$mate_journey = Journey::where('journey_id','=',$mate_id)->first();
					array_push($mates, intval($mate_journey->id));
				}
				$send_group->users_list = $mates;
				$send_group->path = NULL;
				return Error::success("Already on a Journey!",array(
					'group_id'=>intval($journey->group_id) ,
					'group' => $send_group,
					)
				);
			}
		}

		// User details of person
		$new_user = User::where('id','=',$journey->id)->first();

		$best_match = json_decode($journey->best_match);
		// Case where a best match exists
		if (!is_null($best_match))
		{
			$group = Group::where('group_id','=',$best_match->id)->first();
			// TODO :- if condition not needed
			if (is_null($group))
				return Error::make(1,17);

			// Updating journey_ids field in Group.
			$people_so_far=json_decode($group->journey_ids);
			array_push($people_so_far,$journey_id);
			$new_path_waypoints = self::getwaypoints($journey_id,$group->group_id);

			// Updating required fields.
			try {
				Group::where('group_id','=',$group->group_id)->update(array(
					'journey_ids' => json_encode($people_so_far),
					'path_waypoints' => json_encode($new_path_waypoints),
				));
				Journey::where('journey_id','=',$journey_id)->update(array(
					'group_id' => $group->group_id,
				));
				self::generate_group_path($group->group_id);
			} catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}

			// Sending push notifications to people travelling in
			// that group.
			$push_data = array(
				'user_id'=>intval($journey->id),
				'user_name'=>$new_user->first_name,
				'fbid'=>$new_user->fbid
				);
			self::send_push($people_so_far,10,$push_data);
			// Driver push notification
			if (!is_null($group->driver_id))
			{
				self::driver_send_push(array(intval($group->driver_id)),18,array(
						'user_id'=>intval($journey->id),
						'user_name'=>$new_user->first_name,
						'group_id'=>$group->group_id
						)
					);
			}

			// Building data set to send to user confirming journey.
			$send_group = Group::where('group_id','=',$group->group_id)->first();
			// List of user IDs of all mates.
			$mates=array();
			foreach ($people_so_far as $mate_id) {
				$mate_journey = Journey::where('journey_id','=',$mate_id)->first();
				array_push($mates, intval($mate_journey->id));
			}
			$send_group->users_list = $mates;
			$send_group->path = NULL;

			return Error::success("Group successfully confirmed!",array(
				'group_id'=>intval($group->group_id),
				'group' => $send_group,
			));

		}

		// Let's create a new group!
		else {
			$group = new Group;
			// Arrays to see state of journey.
			// TODO :- Think of a better representation?
			$group->journey_ids = json_encode(array(intval($journey_id),));
			$group->people_on_ride = json_encode(array());
			$group->completed = json_encode(array());

			// Sequence of events occuring along with point where
			// it occurred.
			$group->event_sequence = json_encode(array(
				"journey_ids"=>array(),
				"points"=>array()
				)
			);

			// Time window to accept journeys should be between
			// start time and journey time. Coded in find_mates()
			$group->journey_time = $journey->journey_time;
			$group->start_time = date('Y-m-d G:i:s',
				strtotime($journey->journey_time)-$journey->margin_after*60);
			
			$group->path_waypoints = json_encode(self::getwaypoints(intval($journey_id)));
			
			// Can be started, completed, or confirmed.
			// TODO :- Code a cancelled event status in cancel_journey as well.
			$group->event_status = "confirmed";
			
			// Making database changes.
			try {
				$group->save();
				self::generate_group_path($group->id);
				Journey::where('journey_id','=',$journey_id)->update(array(
					'group_id' => $group->id,
				));
			} catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}

			// Sending group data to the user.
			$group->users_list = array();
			$group->path_waypoints = json_decode($group->path_waypoints);
			$group->path = NULL;
			return Error::success("Group successfully confirmed!",array(
				'group_id'=>$group->id,
				'group' => $group,
			));
		}
	}


	/**
	 * Helper function to find dot products of unit vectors along
	 * directions of input.
	 *
	 * Standard dot product function which converts vectors to 
	 * unit vectors and finds their dot product. Returns 1 in 
	 * the case any one magnitude is 0.
	 *
	 * @param float[2] $vector1 The X,Y coordinates of first vector.
	 * @param float[2] $vector2 The X,Y coordinates of second vector.
	 * @return float The dot product of the vectors.
	 */
	public function find_dot_product_units($vector1,$vector2)
	{
		$magnitude1 = sqrt($vector1[0]*$vector1[0]+$vector1[1]*$vector1[1]);
		$magnitude2 = sqrt($vector2[0]*$vector2[0]+$vector2[1]*$vector2[1]);
		if ($magnitude1==0 || $magnitude2==0)
		return 1;

		// Converting to unit vectors.
		$vector1[0] = $vector1[0]/$magnitude1;
		$vector1[1] = $vector1[1]/$magnitude1;
		$vector2[0] = $vector2[0]/$magnitude2;
		$vector2[1] = $vector2[1]/$magnitude2;

		// Dot product.
		return ($vector1[0]*$vector2[0]+$vector1[1]*$vector2[1]);
	}


	/**
	 * Helper function to sort the start and end points of a group.
	 *
	 * This function is used to get the new path_waypoints array in a
	 * group object. It fits the new start and end point into the existing
	 * order using a vector dot product approach. It is one of the most
	 * important functions in this product.
	 * TODO :- Move startwaypoints and endwaypoints into single array 
	 *
	 * @param int $journey_id The Journey ID of the person joining the
	 * group.
	 * @param int $group_id The group to which the person is being added.
	 * Kept as 0 if a new group is being formed.
	 * @return mixed[] The new value for path_waypoints
	 */
	public function getwaypoints($journey_id,$group_id=0)
	{
		$journey=Journey::where('journey_id','=',$journey_id)->first();
		// Case where a new group is being created.
		if ($group_id==0)
		{
			$final_path=array(

				'startwaypoints'=>array(array(
					floatval($journey->start_lat),
					floatval($journey->start_long))),

				'endwaypoints'=> array(array(
					floatval($journey->end_lat),
					floatval($journey->end_long))),

				'start_order'=>array($journey_id),

				'end_order'=>array($journey_id),
			);
			return $final_path;
		}

		else
		{
			// Case when person is added to existing group

			// Original path_waypoints
			$final_path=json_decode(Group::where('group_id','=',$group_id)->
									first()->path_waypoints);

			// Order startwaypoints using direction vector.
			// Direction vector is the approx. general direction of travel.
			// It is vector from first start point to first end point.
			$direction_vector = array(
				$final_path->endwaypoints[0][0]-$final_path->startwaypoints[0][0],
				$final_path->endwaypoints[0][1]-$final_path->startwaypoints[0][1]
				);

			// New set of coordinates to be added
			$current_coordinates_start=array(
				floatval($journey->start_lat),
				floatval($journey->start_long)
				);
			$current_coordinates_end=array(
				floatval($journey->end_lat),
				floatval($journey->end_long)
				);

			// Choosing which position for the new point is most suited.
			$suitable_start_position=0;
			$largest_dot_product=-10000000;

			for ($i=0;$i<sizeof($final_path->startwaypoints)+1;$i++)
			{
				// Testing suitability of position $i
				$startwaypoints=$final_path->startwaypoints;
				array_splice($startwaypoints, $i, 0, array($current_coordinates_start));
				
				// Idea is to find sum of dot products of consecutive vectors
				// with general direction vector. Maximizing this sum is the aim.
				$graph_vectors = array();
				for ($j=0;$j<sizeof($startwaypoints)-1;$j++)
				{
					array_push($graph_vectors,array(
						$startwaypoints[$j+1][0]-$startwaypoints[$j][0],
						$startwaypoints[$j+1][1]-$startwaypoints[$j][1]
						)
					);
				}

				// Summing dot products
				$dot_product=0;
				for ($j=0;$j<sizeof($graph_vectors);$j++)
					$dot_product+=self::find_dot_product_units (
							$direction_vector,
							$graph_vectors[$j]
						);
				
				if ($dot_product>$largest_dot_product)
				{
					$suitable_start_position=$i;
					$largest_dot_product=$dot_product;
				}
			}

			// Adding new point in chosen location.
			array_splice($final_path->start_order,$suitable_start_position,
						 0,$journey_id);
			array_splice($final_path->startwaypoints,$suitable_start_position,
						 0,array($current_coordinates_start));

			// Same procedure for end points.

			// Choosing which position for the new point is most suited.
			$suitable_end_position=0;
			$largest_dot_product=-10000000;

			for ($i=0;$i<sizeof($final_path->endwaypoints)+1;$i++)
			{
				// Testing suitability of position $i
				$endwaypoints=$final_path->endwaypoints;
				array_splice($endwaypoints, $i, 0, array($current_coordinates_end));
				
				// Idea is to find sum of dot products of consecutive vectors
				// with general direction vector. Maximizing this sum is the aim.
				$graph_vectors = array();
				for ($j=0;$j<sizeof($endwaypoints)-1;$j++)
				{
					array_push($graph_vectors,array(
						$endwaypoints[$j+1][0]-$endwaypoints[$j][0],
						$endwaypoints[$j+1][1]-$endwaypoints[$j][1]
						)
					);
				}
				
				// Summing dot products
				$dot_product=0;
				for ($j=0;$j<sizeof($graph_vectors);$j++)
					$dot_product=$dot_product+self::find_dot_product_units (
							$direction_vector,
							$graph_vectors[$j]
						);
				
				if ($dot_product>$largest_dot_product)
				{
					$suitable_end_position=$i;
					$largest_dot_product=$dot_product;
				}
			}

			// Adding new point in chosen location.
			array_splice($final_path->end_order,$suitable_end_position,
						 0,$journey_id);
			array_splice($final_path->endwaypoints,$suitable_end_position,
						 0,array($current_coordinates_end));

			return $final_path;
		}
	}


	/**
	 * Helper function to swap two of the three paths in a Journey
	 * object of the provided journey_id.
	 *
	 * The function swaps database entries. Takes care of NULL paths too. 
	 *
	 * @param int $journey_id The Journey ID whose paths we want to swap.
	 * @param int $path1 The first path number. (1,2 or 3)
	 * @param int $path2 The second path number. (1,2 or 3)
	 */
	public function swap_paths($journey_id,$path1,$path2)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		
		try {
			// Iterating all possible cases of path inputs.
			if (($path1==1 && $path2==2) || ($path1==2 && $path2==1))
				Journey::where('journey_id','=',$journey_id)->update(array(
					'path' => $journey->path2,
					'path2' => $journey->path,
				));
			else if (($path1==1 && $path2==3) || ($path1==3 && $path2==1))
				Journey::where('journey_id','=',$journey_id)->update(array(
					'path' => $journey->path3,
					'path3' => $journey->path,
				));
			else if (($path1==2 && $path2==3) || ($path1==3 && $path2==2))
				Journey::where('journey_id','=',$journey_id)->update(array(
					'path2' => $journey->path3,
					'path3' => $journey->path2,
				));

			// Damage control by converting "" to NULL.
			$journey = Journey::where('journey_id','=',$journey_id)->first();
			if ($journey->path2=="")
				Journey::where('journey_id','=',$journey_id)->update(array(
					'path2' => NULL,
				));
			if ($journey->path3=="")
				Journey::where('journey_id','=',$journey_id)->update(array(
					'path3' => NULL,
				));

		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}


	/**
	 * Helper function to find the most suitable matches for a given
	 * set of conditions.
	 *
	 * This function is the key to success. Handle with care.
	 * This function is used to get suitable mates for a given journey id.
	 * It performs matches using time windows, and a graining algorithm.
	 * It can be used to find matches for groups with just one person, or 
	 * groups having 2 or more people. $check_individual decides that.
	 * TODO :- reduce function size
	 *
	 * @param int $journey_id The ID of the Journey whose mates we want.
	 * @param int $request_path_number Path of requestee we wish to find mates for.
	 * Can be 1,2 or 3 depending on which path stored in Journey we want to check.
	 * @param int $test_path_number Paths of individual already in group we wish to 
	 * match against. Can be 1,2 or 3.
	 * @param boolean $check_individual True if we want to match against groups
	 * having a single person.
	 * @param int $margin_after The time upto which matches are permitted.
	 * @return mixed[] Journey details array.
	 */
	public function find_mates($journey_id=0,$request_path_number=1,$test_path_number=1,
							   $check_individual=False,$margin_after=30)
	{
		// Check validity of $journey_id
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}

		// Checking for mates at time margin_after before journey_time
		$t1 = date('Y-m-d G:i:s',strtotime($journey->journey_time)-$margin_after*60);
		
		$pending = Group::where('journey_time' , '>=' , $t1 )->
						  where('start_time' , '<' , $t1 )->
						  where('event_status','=','confirmed')->
						  get();

		// Match amount for top n people
		$topn_weights = array();
		// Top 5 matched group ids
		$corresponding_ids = array();
		// Maximum distance allowed between start points
		$distance_threshold=2;
		// Maximum people in a ride
		$max_people=3;
		// We compute top n matches
		$n=5;

		// 0.5 is the minimum permissible match amount
		for ($i=0;$i<$n;$i++)
		{
			$topn_weights[$i]=0.5;
			$corresponding_ids[$i]=0;
		}

		// Deciding the requestee path variable
		$request_path=$journey->path;
		if ($request_path_number==2)
		$request_path=$journey->path2;
		else if ($request_path_number==3)
		$request_path=$journey->path3;
		
		// Array is used to choose which groups have 1 person and which
		// have more, depending on the choice of $check_individual
		$temp_pending=array();
		for ($i=0;$i<sizeof($pending);$i++)
		{
			$num_people = sizeof(json_decode($pending[$i]->journey_ids));
			if ($num_people==1 && $check_individual==False)
			continue;
			if ($num_people>1 && $check_individual==True)
			continue;
			array_push($temp_pending, $pending[$i]);
		}
		$pending=$temp_pending;

		// Go over each of the groups obtained
		for ($i=0;$i<sizeof($pending);$i++)
		{
			$people_so_far = json_decode($pending[$i]->journey_ids);

			// Choose path to be worked on if group has one person
			if ($check_individual==True)
			{
				$individual_journey = Journey::where('group_id','=',$pending[$i]->group_id)->first();
				if ($test_path_number==1)
				$pending[$i]->path = $individual_journey->path;
				else if ($test_path_number==2)
				$pending[$i]->path = $individual_journey->path2;
				else if ($test_path_number==3)
				$pending[$i]->path = $individual_journey->path3;
			}

			$test_path=$pending[$i]->path;
			// That path doesn't exist for given person
			if (is_null($test_path) || is_null($request_path))
				continue;

			// Check number of matches
			$matchArray = Graining::countMatches(json_decode($request_path),json_decode($test_path));
			$matches = $matchArray[0];
			$same_direction = $matchArray[1];
			// Points in first path
			$count1 = 0;
			foreach (json_decode($request_path) as $key=>$value) {
				$count1++;
			}
			// Points in second path
			$count2 = 0;
			foreach (json_decode($test_path) as $key=>$value) {
				$count2++;
			}
			// Old formula mentioned below :-
			//$weighted = (5*$matches - 2.5*($count1-$matches) - 2.5*($count2-$matches))/(5*$count1);
			$weighted = (($matches/$count1)+($matches/$count2))/2;
			
			// Filters
			// Checking direction of travel
			if ($same_direction==0)
				continue; //Opposite directions
			// Checking availability in the car
			if (sizeof($people_so_far)>=$max_people)
				continue;
			
			// New group good enough to join the top n
			if ($weighted>=$topn_weights[$n-1])
			{
				$topn_weights[$n-1]=$weighted;
				$corresponding_ids[$n-1]=$pending[$i]->group_id;
			}

			// Inserting value into arrays in numerical order
			for ($j=$n-2;$j>=0;$j--)
			{
				if ($topn_weights[$j+1]>$topn_weights[$j])
				{
					$temp=$topn_weights[$j];
					$topn_weights[$j]=$topn_weights[$j+1];
					$topn_weights[$j+1]=$temp;

					$temp2=$corresponding_ids[$j];
					$corresponding_ids[$j]=$corresponding_ids[$j+1];
					$corresponding_ids[$j+1]=$temp2;
				}
			}
		}

		// Sending just the IDs isn't enough
		// Sending complete group object, user ids,
		// and match amount in a percentage format.
		$final_data=array();
		for ($i=0;$i<sizeof($corresponding_ids);$i++)
		{
			$temp_id=intval($corresponding_ids[$i]);
			// Less than n matches found
			if ($temp_id==0)
			{
				$final_data[$i]=NULL;
				continue;
			}

			$temp_group = Group::where('group_id','=',$temp_id)->first();
			// Should never be executed. Safety check. TODO :- remove this
			if(is_null($temp_group)){
				return Error::make(1,10);
			}

			// Getting user ids of all people in that group
			$people_so_far=json_decode($temp_group->journey_ids);
			$user_ids=array();
			foreach ($people_so_far as $journey_id1) {
				$journey_details = Journey::where('journey_id','=',$journey_id1)->first();
				$user = User::where('id' , '=',intval($journey_details->id))->first();
				array_push($user_ids, $user->id);
			}

			// path has unnecessary data
			$temp_group->path=NULL;
			$temp_group->user_ids=$user_ids;
			$final_data[$i]=$temp_group;
			$final_data[$i]->match_amount=$topn_weights[$i]*100;
		}

		// Sent as Error::success object. TODO :- remove this format.
		$jsonobject = array("error" => 0, "message" => "ok" , "mates"=>$final_data);
		return $jsonobject;
	}


	/**
	 * Returns the journey object depending on journey ID.
	 *
	 * This route returns the journey object requested for. 
	 * Simple public route, extensively used.
	 * 
	 * Implemented in the route :- <br>
	 * Route::get('get_pending/{id}/' , 'HomeController@get_pending'); <br>
	 * Here 'id' refers to the journey ID.
	 *
	 * @param int $journey_id The ID whose Journey we wish for. 
	 * @return mixed[] Journey details array.
	 */
	public function get_pending($journey_id)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);
		
		// Unnecesary data turned to NULL.
		$journey->path=NULL;
		return $journey;
	}


	/**
	 * Route to cancel journey when user hasn't yet gone in the car.
	 *
	 * This function acts as a route. It is used to pop journey objects
	 * from the group objects and send push notifications for the same.
	 * Also called by journey_add in extreme cases.
	 * TODO :- Move to POST request?
	 * 
	 * Implemented in the route :- <br>
	 * Route::get('cancel_journey/{id}','HomeController@cancel_journey');
	 *
	 * @param int $journey_id The ID whose Journey we wish for. 
	 * @return mixed[] Response object with appropriate message.
	 */
	public function cancel_journey($journey_id)
	{
		// Getting journey object.
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);

		$user = User::where('id','=',$journey->id)->first();
		// Valid user checked for while adding journey.

		$group = Group::where('group_id','=',intval($journey->group_id))->first();
		// Safety check. Should never execute.
		if(is_null($group))
			return Error::make(1,17);

		// List of people who have completed the journey.
		// Not allowed to cancel journey if you have completed
		// the journey.
		$completed = json_decode($group->completed);
		if (in_array($journey_id, $completed)) {
			return Error::make(1,42);
		}

		// List of people who are in the journey.
		// Not allowed to cancel journey if you are in
		// the journey.
		$people_on_ride = json_decode($group->people_on_ride);
		if (in_array($journey_id, $people_on_ride)) {
			return Error::make(1,44);
		}

		$people_so_far = json_decode($group->journey_ids);
		$path = json_decode($group->path_waypoints);

		// Popping journey ID elements from required arrays.
		if(($key = array_search($journey_id, $people_so_far)) !== false) {
			array_splice($people_so_far, $key, 1);
		}
		if(($key = array_search($journey_id, $path->start_order)) !== false) {
			array_splice($path->start_order, $key, 1);
			array_splice($path->startwaypoints, $key, 1);
		}
		if(($key = array_search($journey_id, $path->end_order)) !== false) {
			array_splice($path->end_order, $key, 1);
			array_splice($path->endwaypoints, $key, 1);
		}

		// Case where group doesn't have any person.
		if (sizeof($people_so_far)==0)
		{
			if (!is_null($group->driver_id))
			{
				// Deallocate driver, if any.
				Driver::where('driver_id','=',$group->driver_id)->update(array(
					'driver_status'=>'vacant',
					'group_id'=>NULL,
					)
				);
				//TODO :- Notify Driver
			}
			// Delete group object.
			Group::where('group_id','=',$group->group_id)->delete();		
		}

		else
		{
			Group::where('group_id','=',$group->group_id)->update(array(
				'journey_ids' => json_encode($people_so_far),
				'path_waypoints' => json_encode($path),
			));
			if (!is_null($group->driver_id))
			{
				// Notifying driver about cancelling of a user.
				self::driver_send_push(array(intval($group->driver_id)),17,array(
					'user_id'=>intval($journey->id),
					'user_name'=>$user->first_name,
					'group_id'=>$group->group_id
					)
				);
			}
		}
		// Set group id to cancelled value.
		Journey::where("journey_id","=",$journey_id)->update(array(
				'group_id'=>-1,
			));

		// Generate the new path
		self::generate_group_path($group->group_id);
		$push_data = array(
			'user_id'=>intval($journey->id),
			'user_name'=>$user->first_name
			);
		// Send push notification to all people still travelling
		self::send_push($people_so_far,13,$push_data);
		return Error::success("Journey Cancelled successfully!!",array(
			'journey_id'=>intval($journey_id)
			)
		);
	}


	/**
	 * Fundamental route to edit a journey request.
	 *
	 * This function is implemented under the route if edit request
	 * is within +/- 1 hour of journey_time, Journey object is created 
	 * and group is still not allotted. :-
	 * <br>
	 * Route::post('add_journey', array('as' => 'journey.add',
	 * 'uses' => 'HomeController@journey_add'));
	 * 
	 * Parameters required by route :- <br>
	 * <b>user_id</b> <i>int</i> - User ID of person registering journey.<br>
	 * <b>margin_after</b> <i>string</i> - Period user is ready to wait for.<br>
	 * <b>alternate_journey_time</b> <i>string</i> - (optional parameter)
	 * Used to send future journey_time for website bypass.<br>
	 * <b>start_lat</b> <i>float</i> - Latitude of start point. <br>
	 * <b>start_long</b> <i>float</i> - Longitude of start point. <br>
	 * <b>end_lat</b> <i>float</i> - Latitude of end point. <br>
	 * <b>end_long</b> <i>float</i> - Longitude of end point. <br>
	 * <b>margin_before</b> <i>string</i> - deprecated, TODO :- remove. <br>
	 * <b>preference</b> <i>string</i> - Choice of car. Between 1 and 5.<br>
	 * <b>start_text</b> <i>string</i> - Name of starting point. <br>
	 * <b>end_text</b> <i>string</i> - Name of ending point. <br>
	 * 
	 * @return mixed[] Error::success type Response with journey_id and
	 * journey_time
	 */
	public function journey_edit($journey_id) {
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);
		
		$requirements = ['start_lat', 'start_long', 'end_lat', 'end_long', 
						 'user_id', 'margin_after' , 'margin_before', 'preference', 
						 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		// Getting Google Directions API path.
		$path = $this->find_path(Input::get('start_lat'), 
								 Input::get('start_long'), 
								 Input::get('end_lat'), 
								 Input::get('end_long'), array(), 1);
		// Path not found.
		if(is_null($path) || (is_int($path) && $path==0)) {
			return Error::make(0,3);
		}
		
		$path1=NULL;
		$path2=NULL;
		$path3=NULL;
		// TODO :- if condition not needed now.
		if (array_key_exists(0, $path->routes))
		{
			$path1 = $path->routes[0];	
			$start_location = self::get_address(Input::get('start_lat'),
												Input::get('start_long'));
			$end_location = self::get_address(Input::get('end_lat'),
											  Input::get('end_long'));
			if ($start_location==0 || $end_location==0)
				return Error::make(1,36);
			if (strpos($start_location['city'],'Mumbai') === false && 
				strpos($start_location['city'],'Thane') === false) {
    			return Error::make(1,36);
			}
			if (strpos($end_location['city'],'Mumbai') === false && 
				strpos($end_location['city'],'Thane') === false) {
    			return Error::make(1,36);
			}
		}
		else
			return Error::make(1,23);

		// Storing path2 if exists
		if (array_key_exists(1, $path->routes))
			$path2 = $path->routes[1];
		// Storing path3 if exists
		if (array_key_exists(2, $path->routes))
			$path3 = $path->routes[2];

		// Google path distance
		$distance  = 0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time = 0;

		// Time estimate by Google
		$path_time=0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$path_time += $value->duration->value;
		}

		// Journey > 100 km
		if($distance > 100000)
			return Error::make(1,4);

		// Valid user
		$user = User::find(Input::get('user_id'));
		if(is_null($user))
			return Error::make(1,1);

		// Used to set journey in future for web bypass. Else use time()
		if(Input::has('alternate_journey_time')) {
			$timestamp=date('Y-m-d H:i:s',strtotime(Input::get('alternate_journey_time'))+intval(Input::get('margin_after'))*60);
		}
		else
			$timestamp=date('Y-m-d H:i:s', time()+intval(Input::get('margin_after'))*60);

		// Valid margin_after and margin_before
		if(!(is_numeric(Input::get('margin_after')) && is_numeric(Input::get('margin_before'))))
			return Error::make(1,7);
		if(Input::get('margin_after') > 60 || Input::get('margin_before') >60 || 
		   Input::get('margin_after') < 0 || Input::get('margin_before') < 0)
			return Error::make(1,7);

		// Valid preference
		if(!is_numeric(Input::get('preference')) || Input::get('preference') > 5 || 
					   Input::get('preference') < 1 )
			return Error::make(1,8);

		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*3);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*3);;

		// Journey edit only allowed in time bracket +/- 3 hours
		$journey = Journey::where('id' , '=' , Input::get('user_id'))->where('journey_id' , '!=' , $journey_id)->where('journey_time' , '>' , $t2 )->where('journey_time' , '<' , $t1 )->first();
		if($this->debug > 0)
			if(!is_null($journey))
				return Error::make(1,9);

		// Store path after appropriate Graining
		$final_path2 = NULL;
		if (!is_null($path2))
			$final_path2 = json_encode(Graining::get_hashed_grid_points(json_encode($path2)));
		$final_path3 = NULL;
		if (!is_null($path3))
			$final_path3 = json_encode(Graining::get_hashed_grid_points(json_encode($path2)));

		try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'group_id' => NULL,
				'start_lat' => Input::get('start_lat'),
				'start_long' => Input::get('start_long'),
				'end_lat' => Input::get('end_lat'),
				'end_long' => Input::get('end_long'),
				'start_text' => Input::get('start_text'),
				'end_text' => Input::get('end_text'),
				'id' => intval(Input::get('user_id')),
				'path' => json_encode(Graining::get_hashed_grid_points(json_encode($path1))),
				'path2' => $final_path2,
				'path3' => $final_path3,
				'journey_time' => $timestamp,
				'margin_before' => Input::get('margin_before'),
				'margin_after' => Input::get('margin_after'),
				'preference' => Input::get('preference'),
				'distance' => $distance,
				'time' => $path_time,
			));

			return Error::success("Journey Edited successfully",
				array('journey_id'=>intval($journey_id),'journey_time'=>$timestamp));
		} 
		catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}


	/**
	 * Helper route to delete journey objects.
	 *
	 * Implemented by :- <br>
	 * Route::any('delete_journey/{id}', 
	 * 	array('as' => 'journey.delete', 'uses' => 'HomeController@journey_delete'));
	 *
	 * @param int $journey_id The ID whose Journey we wish for. 
	 * @return mixed[] Journey details array.
	 */
	public function journey_delete($journey_id){
		$journey = Journey::where('journey_id','=',$journey_id)->delete();
		return Error::success("Journey successfully Deleted");
	}
	
}
