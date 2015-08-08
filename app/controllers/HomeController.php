<?php
function cmp($a,$b){
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


	public function find_path($lat1 = 0 , $log1 = 0 , $lat2 = 0 , $log2 = 0 , $waypoints= array(), $flag=0){
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
		usort($path,'cmp');
		if ($flag==1)
		{
			return $address;
		}
		if(sizeof($path)==0)
		return 0;
		return $path;
	}

	public function journey_add(){

		$requirements = ['user_id','margin_after'];
		$check  = self::check_requirements($requirements);
		if($check)
		return Error::make(0,100,$check);
		$timestamp=date('Y-m-d H:i:s', time()+intval(Input::get('margin_after'))*60);//Input::get('journey_time');
		
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*1);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*1);;

		$check_existing_journey = Journey::where('id' , '=' , intval(Input::get('user_id')))->
											where('journey_time' , '>' , $t2 )->
											where('journey_time' , '<' , $t1 )->
											where(function($query)
            								{
                								$query->whereNull('group_id')
                      							->orWhere('group_id', '!=', -1);
            								})->first();
        //print_r($check_existing_journey);
		if (!is_null($check_existing_journey))
		{
			$editIntention=False;
			if (is_null($check_existing_journey->group_id))
				$editIntention=True;
			if ($editIntention==True)
				return $this->journey_edit($check_existing_journey->journey_id);
			else
			{
				if (intval($check_existing_journey->group_id)!=-1)
				{
					self::cancel_journey(intval($check_existing_journey->journey_id));
				}
			}
			/*
			if(Input::has('journey_id') && $isCancelled==FALSE){
			return $this->journey_edit(Input::get('journey_id'));
			}*/
		}
		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
		return Error::make(0,100,$check);
		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'), array(), 1);
		if(is_null($path)){
			return Error::make(0,3);
		}
		$path1=NULL;
		$path2=NULL;
		$path3=NULL;
		if (array_key_exists(0, $path->routes))
		{
			$path1 = $path->routes[0];	
			foreach ($path1->legs as $leg) {
				$end_address = $leg->end_address;
				$start_address = $leg->start_address;
				if ((strpos($start_address,'Mumbai') == false && strpos($start_address,'Thane') == false)
					||  (strpos($end_address,'Mumbai') == false && strpos($end_address,'Thane') == false))
    				return Error::make(1,36);
			}
		}
		else
			return Error::make(1,23);
		if (array_key_exists(1, $path->routes))
		$path2 = $path->routes[1];
		if (array_key_exists(2, $path->routes))
		$path3 = $path->routes[2];
		$distance  = 0;
		foreach ($path1->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time  = 0;
		$path_time = 0;
		foreach ($path1->legs as $key => $value) {
			$path_time += $value->duration->value;
		}
		if($distance > 100000)
		return Error::make(1,4);

		$user = User::find(Input::get('user_id'));
		if(is_null($user))
		return Error::make(1,1);

		/*$sec = strtotime($timestamp);
		$timenow = time();
		if($timenow > $sec)
		return Error::make(1,6);*/

		if(!(is_numeric(Input::get('margin_after')) && is_numeric(Input::get('margin_before'))))
		return Error::make(1,7);

		if(Input::get('margin_after') > 60 || Input::get('margin_before') >60 || Input::get('margin_after') < 0 || Input::get('margin_before') < 0)
		return Error::make(1,7);

		if(!is_numeric(Input::get('preference')) || Input::get('preference') > 5 || Input::get('preference') < 1 )
		return Error::make(1,8);

		

		$journey = new Journey;


		$journey->start_lat = Input::get('start_lat');
		$journey->start_long = Input::get('start_long');
		$journey->end_lat = Input::get('end_lat');
		$journey->end_long = Input::get('end_long');
		//$json_path=self::find_path($journey->start_lat,$journey->start_long,$journey->end_lat,$journey->end_long,array(),1);
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

		try {
			$journey->save();
			$group_id=0;

			return Error::success("Journey successfully Registered",array('journey_id'=>$journey->id,'journey_time'=>$timestamp));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

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
		//Match with groups first
		//Match with individual people in a group. Use all paths
		for ($i=1;$i<=3;$i++)
		{
			$match_array = self::find_mates($journey_id,$i,1,False)['mates'];
			self::log_matches("Matching route ".$i." of journey_id ".$journey_id." with groups...\n");
			$log_data=print_r($match_array,true);
			self::log_matches($log_data);
			$match = $match_array[0];
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
			self::swap_paths($journey_id,1,$path_number);
		}
		if ($ideal_match_found==False)
		{
			for ($i=1;$i<=3;$i++)
			{
				for ($j=1;$j<=3;$j++)
				{
					$match_array = self::find_mates($journey_id,$i,$j,True)['mates'];
					self::log_matches("Matching route ".$i." of journey_id ".$journey_id." with lonely group journey ".$j."...\n");
					$log_data=print_r($match_array,true);
					self::log_matches($log_data);
					$match = $match_array[0];
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
				self::swap_paths($journey_id,1,$path_number);
			}
		}
		if (!is_null($best_match))
		{
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
			try {
				Journey::where('journey_id','=',$journey_id)->update(array(
					'best_match' => NULL,
				));
			}
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}
		$msg="Mates found!";
		if (is_null($best_match))
		{
			$best_match=json_decode ("{}");
			$msg="No Mates found!";
		}

			$final_data = array("match_amount"=>$best_match_value,"best_match"=>$best_match);
			return Error::success($msg,$final_data);
	}

	public function get_group($group_id=0)
	{
		$group = Group::where('group_id','=',$group_id)->first();
		if(is_null($group)){
			return Error::make(1,1);
		}
		$group->path=null;
		return Error::success("Group Details..",$group);
	}

	public function generate_group_path($group_id)
	{
		if(is_null(Group::where('group_id','=',$group_id)->first()))
			return;
		$final_path=json_decode(Group::where('group_id','=',$group_id)->first()->path_waypoints);
		$waypoints=array();
		for ($j=1;$j<sizeof($final_path->startwaypoints);$j++)
		{
			array_push($waypoints,$final_path->startwaypoints[$j]);
		}
		for ($j=0;$j<sizeof($final_path->endwaypoints)-1;$j++)
		{
			array_push($waypoints,$final_path->endwaypoints[$j]);
		}
		$path=self::find_path($final_path->startwaypoints[0][0],$final_path->startwaypoints[0][1],
		end($final_path->endwaypoints)[0],end($final_path->endwaypoints)[1],$waypoints,1)->routes[0];
		$hashed_path = json_encode(Graining::get_hashed_grid_points(json_encode($path)));
		Group::where('group_id','=',$group_id)->update(array(
			'path' => $hashed_path,
		));
	}

	public function add_to_group($journey_id)
	{
		$journey_id=intval($journey_id);
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		if (!is_null($journey->group_id)){
			$send_group=Group::where('group_id','=',$journey->group_id)->first();
			if(!is_null($send_group)){
				$mates=array();
				foreach (json_decode($send_group->journey_ids) as $mate_id) {
					if ($mate_id==$journey_id)
						continue;
					$mate_journey = Journey::where('journey_id','=',$mate_id)->first();
					array_push($mates, intval($mate_journey->id));
					# code...
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

		$new_user = User::where('id','=',$journey->id)->first();

		$best_match = json_decode($journey->best_match);
		//Convert best_match data to good format
		if (!is_null($best_match))
		{
			//echo "Best match percent is " . $best_match_value ;

			$group = Group::where('group_id','=',$best_match->id)->first();
			if (is_null($group))
				return Error::make(1,17);
			$people_so_far=json_decode($group->journey_ids);
			$push_data = array('user_id'=>intval($journey->id),'user_name'=>$new_user->first_name,
								'fbid'=>$new_user->fbid);
			self::send_push($people_so_far,10,$push_data);
			$mates=array();
			foreach ($people_so_far as $mate_id) {
				$mate_journey = Journey::where('journey_id','=',$mate_id)->first();
				array_push($mates, intval($mate_journey->id));
				# code...
			}
			//Notifying new user about all existing users

			array_push($people_so_far,$journey_id);
			try {
				Group::where('group_id','=',$group->group_id)->update(array(
					'journey_ids' => json_encode($people_so_far),
					'path_waypoints' => json_encode(self::getwaypoints($journey_id,$group->group_id)),
				));
				Journey::where('journey_id','=',$journey_id)->update(array(
					'group_id' => $group->group_id,
				));
				self::generate_group_path($group->group_id);
				$send_group = Group::where('group_id','=',$group->group_id)->first();
				$send_group->users_list = $mates;
				$send_group->path = NULL;
				return Error::success("Group successfully confirmed!",array(
					'group_id'=>intval($group->group_id),
					'group' => $send_group,
				));
			} catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}

		//Conditions for suitable group
		//0 is false, 1 is true
		else {
			$group = new Group;
			$group->journey_ids = json_encode(array(intval($journey_id),));
			$group->people_on_ride = json_encode(array());
			$group->journey_time = $journey->journey_time;
			$group->start_time = date('Y-m-d G:i:s',strtotime($journey->journey_time)-$journey->margin_after*60);
			// 0 is NO
			// 1 is YES
			// -1 is no status
			$group->path_waypoints = json_encode(self::getwaypoints(intval($journey_id)));
			$group->event_status = "confirmed";
			try {
				$group->save();
				self::generate_group_path($group->id);
				Journey::where('journey_id','=',$journey_id)->update(array(
					'group_id' => $group->id,
				));
				$group->users_list = array();
				$group->path_waypoints = json_decode($group->path_waypoints);
				$group->path = NULL;
				return Error::success("Group successfully confirmed!",array(
					'group_id'=>$group->id,
					'group' => $group,
				));
			} catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}
	}

	public function find_dot_product_units($vector1,$vector2)
	{
		$magnitude1 = sqrt($vector1[0]*$vector1[0]+$vector1[1]*$vector1[1]);
		$magnitude2 = sqrt($vector2[0]*$vector2[0]+$vector2[1]*$vector2[1]);
		if ($magnitude1==0 || $magnitude2==0)
		return 1;
		$vector1[0] = $vector1[0]/$magnitude1;
		$vector1[1] = $vector1[1]/$magnitude1;
		$vector2[0] = $vector2[0]/$magnitude2;
		$vector2[1] = $vector2[1]/$magnitude2;
		return ($vector1[0]*$vector2[0]+$vector1[1]*$vector2[1]);
	}

	public function getwaypoints($journey_id,$group_id=0)
	{
		$journey=Journey::where('journey_id','=',$journey_id)->first();
		if ($group_id==0)
		{
			$final_path=array(	'startwaypoints'=>array(array(floatval($journey->start_lat),floatval($journey->start_long))),
				'endwaypoints'=> array(array(floatval($journey->end_lat),floatval($journey->end_long))) ,
				'start_order'=>array($journey_id),
				'end_order'=>array($journey_id),
			);
			return $final_path;
		}
		else
		{
			$final_path=json_decode(Group::where('group_id','=',$group_id)->first()->path_waypoints);
			//Order startwaypoints using direction vector
			$direction_vector = array($final_path->endwaypoints[0][0]-$final_path->startwaypoints[0][0],
			$final_path->endwaypoints[0][1]-$final_path->startwaypoints[0][1]);


			$current_coordinates_start=array(floatval($journey->start_lat),floatval($journey->start_long));
			$current_coordinates_end=array(floatval($journey->end_lat),floatval($journey->end_long));

			$suitable_start_position=0;
			$largest_dot_product=-10000000;

			for ($i=0;$i<sizeof($final_path->startwaypoints)+1;$i++)
			{
				$startwaypoints=$final_path->startwaypoints;
				array_splice($startwaypoints, $i, 0, array($current_coordinates_start));
				$graph_vectors = array();
				for ($j=0;$j<sizeof($startwaypoints)-1;$j++)
				{
					array_push($graph_vectors,array($startwaypoints[$j+1][0]-$startwaypoints[$j][0],
					$startwaypoints[$j+1][1]-$startwaypoints[$j][1]));
				}
				$dot_product=0;
				//print_r($startwaypoints);
				//print_r($graph_vectors);
				//print_r($direction_vector);
				for ($j=0;$j<sizeof($graph_vectors);$j++)
				$dot_product=$dot_product+self::find_dot_product_units($direction_vector,$graph_vectors[$j]);
				//echo "The dot product is ".$dot_product;
				if ($dot_product>$largest_dot_product)
				{
					$suitable_start_position=$i;
					$largest_dot_product=$dot_product;
				}
			}
			array_splice($final_path->start_order,$suitable_start_position,0,$journey_id);
			array_splice($final_path->startwaypoints,$suitable_start_position,0,array($current_coordinates_start));






			$suitable_end_position=0;
			$largest_dot_product=-10000000;

			for ($i=0;$i<sizeof($final_path->endwaypoints)+1;$i++)
			{
				$endwaypoints=$final_path->endwaypoints;
				array_splice($endwaypoints, $i, 0, array($current_coordinates_end));
				$graph_vectors = array();
				for ($j=0;$j<sizeof($endwaypoints)-1;$j++)
				{
					array_push($graph_vectors,array($endwaypoints[$j+1][0]-$endwaypoints[$j][0],
					$endwaypoints[$j+1][1]-$endwaypoints[$j][1]));
				}
				$dot_product=0;
				//print_r($startwaypoints);
				//print_r($graph_vectors);
				//print_r($direction_vector);
				for ($j=0;$j<sizeof($graph_vectors);$j++)
				$dot_product=$dot_product+self::find_dot_product_units($direction_vector,$graph_vectors[$j]);
				//echo "The dot product is ".$dot_product;
				if ($dot_product>$largest_dot_product)
				{
					$suitable_end_position=$i;
					$largest_dot_product=$dot_product;
				}
			}
			array_splice($final_path->end_order,$suitable_end_position,0,$journey_id);
			array_splice($final_path->endwaypoints,$suitable_end_position,0,array($current_coordinates_end));

			//array_push($final_path->start_order,$journey_id);
			//array_push($final_path->startwaypoints,array(floatval($journey->start_lat),floatval($journey->start_long)));
			/*
			$suitable_end_position=0;
			$shortest_distance=10000000;

			for ($i=0;$i<sizeof($final_path->endwaypoints)+1;$i++)
			{
				$waypoints=array();
				for ($j=1;$j<sizeof($final_path->startwaypoints);$j++)
				{
					array_push($waypoints,$final_path->startwaypoints[$j]);
				}
				array_push($waypoints,$current_coordinates_start);
				$endwaypoints=$final_path->endwaypoints;
				array_splice($endwaypoints, $i, 0, array($current_coordinates_end));
				for ($j=0;$j<sizeof($final_path->endwaypoints)-1;$j++)
				{
					array_push($waypoints,$endwaypoints[$j]);
				}
				$test=self::find_path($final_path->startwaypoints[0][0],$final_path->startwaypoints[0][1],
				end($endwaypoints)[0],end($endwaypoints)[1],$waypoints,1)->routes[0]->legs;
				$distance=0;
				for ($k=0;$k<sizeof($test);$k++)
				{
					$distance=$distance+$test[$k]->distance->value;
				}
				if ($distance<$shortest_distance)
				{
					$suitable_end_position=$i;
					$shortest_distance=$distance;
				}
			}
			array_splice($final_path->end_order,$suitable_end_position,0,$journey_id);
			array_splice($final_path->endwaypoints,$suitable_end_position,0,array($current_coordinates_end));
			*/
			return $final_path;
		}
	}

	public function swap_paths($journey_id,$path1,$path2)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		try {
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

	public function find_mates($journey_id=0,$request_path_number=1,$test_path_number=1,$check_individual=False,$margin_after=30)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		$t1 = date('Y-m-d G:i:s',strtotime($journey->journey_time)-$margin_after*60);
		$t2 = date('Y-m-d G:i:s',strtotime($journey->journey_time)+$margin_after*60);
		$pending = Group::where('journey_time' , '>=' , $t1 )->where('start_time' , '<' , $t1 )->get();

		$topn_weights = array();
		$corresponding_ids = array();
		$distance_threshold=2;
		$max_people=5;
		$n=5;
		for ($i=0;$i<$n;$i++)
		{
			$topn_weights[$i]=0.5;
			$corresponding_ids[$i]=0;
		}
		//Matching Pending Journeys
		$request_path=$journey->path;
		if ($request_path_number==2)
		$request_path=$journey->path2;
		else if ($request_path_number==3)
		$request_path=$journey->path3;
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
		for ($i=0;$i<sizeof($pending);$i++)
		{

			//$matches=0;
			//$weighted=0;
			//echo $journey->start_text . $journey->end_text . " " .$pending[$i]->start_text . $pending[$i]->end_text  . "\n";
			$people_so_far = json_decode($pending[$i]->journey_ids);
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
			if (is_null($test_path) || is_null($request_path))
			continue;
			$matchArray = Graining::countMatches(json_decode($request_path),json_decode($test_path));
			$matches = $matchArray[0];
			$same_direction = $matchArray[1];
			$count1 = 0;
			foreach (json_decode($request_path) as $key=>$value) {
				$count1++;
			}
			$count2 = 0;
			foreach (json_decode($test_path) as $key=>$value) {
				$count2++;
			}
			//$weighted = (5*$matches - 2.5*($count1-$matches) - 2.5*($count2-$matches))/(5*$count1);
			$weighted = (($matches/$count1)+($matches/$count2))/2;
			//echo "for the current config, matches are ".$matches." and totals are ".$count1." ".$count2;
			//echo $weighted . " " . $matches . " " . $count1 . " " . $count2 . $pending[$i]->end_text . "\n";
			//$distance=self::distance($journey->start_lat,$journey->start_long,$pending[$i]->start_lat,$pending[$i]->start_long);
			if ($same_direction==0)
			continue; //Opposite directions
			if (sizeof($people_so_far)>=$max_people)
			continue;
			/*if ($distance>$distance_threshold)
			continue;*/
			if ($weighted>=$topn_weights[$n-1])
			{
				$topn_weights[$n-1]=$weighted;
				$corresponding_ids[$n-1]=$pending[$i]->group_id;
			}
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
		$final_data=array();
		for ($i=0;$i<sizeof($corresponding_ids);$i++)
		{
			$temp_id=intval($corresponding_ids[$i]);
			if ($temp_id==0)
			{
				$final_data[$i]=NULL;
				continue;
			}
			$temp_group = Group::where('group_id','=',$temp_id)->first();
			if(is_null($temp_group)){
				return Error::make(1,10);
			}

			$people_so_far=json_decode($temp_group->journey_ids);
			$user_ids=array();
			foreach ($people_so_far as $journey_id1) {
				$journey_details = Journey::where('journey_id','=',$journey_id1)->first();
				$user = User::where('id' , '=',intval($journey_details->id))->first();
				array_push($user_ids, $user->id);
			}
			$temp_group->path=NULL;
			$temp_group->user_ids=$user_ids;
			$final_data[$i]=$temp_group;
			$final_data[$i]->match_amount=$topn_weights[$i]*100;
		}
		$jsonobject = array("error" => 0, "message" => "ok" , "mates"=>$final_data);
		return $jsonobject;

	}

	public function get_pending($journey_id)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
		return Error::make(1,11);
		$journey->path=NULL;

		return $journey;
	}

	public function cancel_journey($journey_id)
	{
		//User can't cancel journeys after ride is done.

		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);

		$user = User::where('id','=',$journey->id)->first();
		$group = Group::where('group_id','=',intval($journey->group_id))->first();
		if(is_null($group))
			return Error::make(1,11);

		$people_on_ride = json_decode($group->people_on_ride);
		if (in_array($journey_id, $people_on_ride)) {
			return Error::success("You can't cancel the ride now!",array('journey_id'=>intval($journey_id)));
		}

		$people_so_far = json_decode($group->journey_ids);
		$path = json_decode($group->path_waypoints);
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
		if (sizeof($people_so_far)==0)
		{
			Group::where('group_id','=',$group->group_id)->delete();
			//Notify Driver
		}
		else
		{
			Group::where('group_id','=',$group->group_id)->update(array(
				'journey_ids' => json_encode($people_so_far),
				'path_waypoints' => json_encode($path),
			));
		}
		Journey::where("journey_id","=",$journey_id)->update(array(
				'group_id'=>-1,
			));
		self::generate_group_path($group->group_id);
		$push_data = array('user_id'=>intval($journey->id),'user_name'=>$user->first_name);
		self::send_push($people_so_far,13,$push_data);
		return Error::success("Journey Cancelled successfully!!",array('journey_id'=>intval($journey_id)));
	}

	public function journey_edit($journey_id) {
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
		return Error::make(1,11);
		try {
			/*
			$group = Group::where('group_id','=',intval($journey->group_id))->first();
			$people_so_far = json_decode($group->journey_ids);
			$path = json_decode($group->path_waypoints);
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
			if (sizeof($people_so_far)==0)
			{
				Group::where('group_id','=',$group->group_id)->delete();
			}
			else
			{
				Group::where('group_id','=',$group->group_id)->update(array(
				'journey_ids' => json_encode($people_so_far),
				'path_waypoints' => json_encode($path),
				));
			}
			self::generate_group_path($group->group_id);
			*/
		}
		catch(Exception $e) {
			return Error::make(1,22);
		}
		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id', 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);

		if($check)
			return Error::make(0,100,$check);

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'), array(), 1);
		if(is_null($path)){
			return Error::make(0,3);
		}
		$path1=NULL;
		$path2=NULL;
		$path3=NULL;
		if (array_key_exists(0, $path->routes))
		{
			$path1 = $path->routes[0];	
			foreach ($path1->legs as $leg) {
				$end_address = $leg->end_address;
				$start_address = $leg->start_address;
				if ((strpos($start_address,'Mumbai') == false && strpos($start_address,'Thane') == false)
					||  (strpos($end_address,'Mumbai') == false && strpos($end_address,'Thane') == false))
    				return Error::make(1,36);
			}
		}
		else
			return Error::make(1,23);

		if (array_key_exists(1, $path->routes))
			$path2 = $path->routes[1];
		if (array_key_exists(2, $path->routes))
			$path3 = $path->routes[2];

		$distance  = 0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time  = 0;
		$path_time=0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$path_time += $value->duration->value;
		}
		if($distance > 100000)
			return Error::make(1,4);

		$user = User::find(Input::get('user_id'));
		if(is_null($user))
			return Error::make(1,1);


		$timestamp=date('Y-m-d H:i:s', time()+intval(Input::get('margin_after'))*60);//Input::get('journey_time');
		/*$sec = strtotime($timestamp);
		$timenow = time();
		if($timenow > $sec)
			return Error::make(1,6);*/

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
		//$json_path=self::find_path($journey->start_lat,$journey->start_long,$journey->end_lat,$journey->end_long,array(),1);
		//echo $timestamp;
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

			return Error::success("Journey Edited successfully",array('journey_id'=>intval($journey_id),'journey_time'=>$timestamp));
		} 
		catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	public function journey_delete($journey_id){
		$journey = Journey::where('journey_id','=',$journey_id)->delete();
		return Error::success("Journey successfully Deleted");
	}


	public function get_journey($id=0,$userData=0)
	{
		$jpair = FinalJourney::find($id);
		if(is_null($jpair)){
			return Error::make(1,10);
		}
		if($jpair->u1==$jpair->u2){
			$jpair->u2_distance = 0;
			$jpair->u2_time = 0;
			$jpair->u2=0;
		}

		$returnObj = array();
		$returnObj['path'] = json_decode($jpair->path);
		$path = json_decode($jpair->path);
		$returnObj['users'] = array();
		$u = array();
		$u[0] = User::find($jpair->u1);
		$u[1] = User::find($jpair->u2);
		$u[2] = User::find($jpair->u3);
		$u1 = array();
		$u1[0] = array();
		$u1[0] = array();
		$u1[0] = array();

		$tot_distance = 0;
		foreach ($path->legs as $key => $leg) {
			$tot_distance += $leg->distance->value;
		}
		$tot_cost = CostCalc::calc($tot_distance);
		if(!is_null($u[0])){
			$old_journey = Journey::where('journey_id' , '=' , $jpair->j1)->first();
			$u1[0]['old_distance'] = $old_journey->distance;
			$u1[0]['old_time'] = $old_journey->time;
			$u1[0]['new_distance'] = $jpair->u1_distance;
			$u1[0]['new_time'] = $jpair->u1_time;
			$u1[0]['old_cost'] = CostCalc::calc($old_journey->distance);
			$u1[0]['new_cost'] = $jpair->u1_distance*($tot_cost / ($jpair->u1_distance + $jpair->u2_distance + $jpair->u3_distance));
		}
		if(!is_null($u[1])){
			$old_journey = Journey::where('journey_id' , '=' , $jpair->j2)->first();
			$u1[1]['old_distance'] = $old_journey->distance;
			$u1[1]['old_time'] = $old_journey->time;
			$u1[1]['new_distance'] = $jpair->u2_distance;
			$u1[1]['new_time'] = $jpair->u2_time;
			$u1[1]['old_cost'] = CostCalc::calc($old_journey->distance);
			$u1[1]['new_cost'] = $jpair->u2_distance*($tot_cost / ($jpair->u1_distance + $jpair->u2_distance + $jpair->u3_distance));
		}
		if(!is_null($u[2])){
			$old_journey = Journey::where('journey_id' , '=' , $jpair->j3)->first();
			$u1[2]['old_distance'] = $old_journey->distance;
			$u1[2]['old_time'] = $old_journey->time;
			$u1[2]['new_distance'] = $jpair->u3_distance;
			$u1[2]['new_time'] = $jpair->u3_time;
			$u1[2]['old_cost'] = CostCalc::calc($old_journey->distance);
			$u1[2]['new_cost'] = $jpair->u3_distance*($tot_cost / ($jpair->u1_distance + $jpair->u2_distance + $jpair->u3_distance));
		}

		foreach ($u as $key => $user) {
			if(!is_null($user)){
				if($user->id != $userData)
				array_push($returnObj['users'], $user);

				if($user->id == $userData){
					$returnObj = array_merge($returnObj , $u1[$key]);
				}
			}
		}


		return $returnObj;
	}
	
	public function modify_location($id=0)
	{
		$jpair = FinalJourney::find($id);
		if(is_null($jpair)){
			return Error::make(1,10);
		}
		$requirements = ['user_id' , 'position'];
		$check  = self::check_requirements($requirements);
		if($check)
		return Error::make(0,100,$check);

		$user = User::find(Input::get('user_id'));
		if(is_null($user))
		return Error::make(1,1);

		User::where('id','=',Input::get('user_id'))->update(array(
			'current_pos' => Input::get('position'),
		));
		$users = array();
		$users[0] = User::find($jpair->u1);
		$users[1] = User::find($jpair->u2);
		$users[2] = User::find($jpair->u3);

		$ret = array();
		foreach ($users as $key => $user) {
			if(!is_null($user)){
				$ret[$user->id] = $user->current_pos;
			}
		}
		return $ret;
	}

	public function event_change($journey_id = 0){
		$jpair = FinalJourney::find($journey_id);
		if(is_null($jpair)){
			return Error::make(1,10);
		}
		$requirements = ['user_id' , 'event'];
		$check  = self::check_requirements($requirements);

		$eventMessage = Input::get('event');
		if($eventMessage != "accept" && $eventMessage != "reject" && $eventMessage != "end_ride" && $eventMessage != "start_ride")
		return Error::make(1,12);

		$user = User::find(Input::get('user_id'));
		if(is_null($user))
		return Error::make(1,1);

		$users = array();
		$users[0] = User::find($jpair->u1);
		$users[1] = User::find($jpair->u2);
		$users[2] = User::find($jpair->u3);

		foreach ($users as $Cuser) {
			if(!is_null($Cuser)){
				if($Cuser->id == $user->id){
					$events = json_decode($jpair->event_status,true);
					$events[$eventMessage][$user->id] = 1;
					FinalJourney::where('id','=',$journey_id)->update(array(
						'event_status' => json_encode($events),
					));
					$ret =  Error::success('Updated status Successfully');
				}
				else{
					$uMsg = array();
					$uMsg['type'] = 10;
					$uMsg['user'] = $user->id;
					$uMsg['event'] = $eventMessage;
					PushNotification::app('Pickup')
					->to($Cuser->registration_id)
					->send(json_encode($uMsg));
				}
			}
		}
		return $ret;
	}
}
