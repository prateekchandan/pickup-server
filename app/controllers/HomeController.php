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
		
		if(Input::has('journey_id')){
			return $this->journey_edit(Input::get('journey_id'));
		}
		
		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'journey_time' , 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'),array(),1);
		if(is_null($path)){
			return Error::make(0,3);
		}
		$distance  = 0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time  = 0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$journey_time += $value->duration->value;
		}
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
		
		$t1 = date('Y-m-d G:i:s', strtotime($timestamp)+3600*1);;
		$t2 = date('Y-m-d G:i:s', strtotime($timestamp)-3600*1);;

		$check_existing_journey = Journey::where('id' , '=' , intval(Input::get('user_id')))->where('journey_time' , '>' , $t2 )->where('journey_time' , '<' , $t1 )->first();
		if(!is_null($check_existing_journey)){
			return $this->journey_edit($check_existing_journey->journey_id);	
		}
		
		$journey = new Journey;
		
		
		$journey->start_lat = Input::get('start_lat');
		$journey->start_long = Input::get('start_long');
		$journey->end_lat = Input::get('end_lat');
		$journey->end_long = Input::get('end_long');
		$json_path=self::find_path($journey->start_lat,$journey->start_long,$journey->end_lat,$journey->end_long,array(),1);
		$journey->path = json_encode(Graining::get_hashed_grid_points(json_encode($json_path)));
		$journey->start_text = Input::get('start_text');
		$journey->end_text = Input::get('end_text');
		$journey->id = Input::get('user_id');
		$journey->journey_time = $timestamp;
		$journey->margin_before = Input::get('margin_before');
		$journey->margin_after = Input::get('margin_after');
		$journey->preference = Input::get('preference');
		$journey->distance = $distance;
		$journey->time = $journey_time;
		
		//try {
			$journey->save();
			$group_id=0;
			
			$group_id=self::add_to_group($journey->id);
			Journey::where('journey_id','=',$journey->id)->update(array(
				'group_id' => $group_id,
			));

			return Error::success("Journey successfully Registered",array('journey_id'=>$journey->id,'group_id'=>$group_id));
		/*} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}*/
	}

	public function get_group($group_id=0)
	{
		$group = Group::find($group_id);
		if(is_null($group)){
			return Error::make(1,1);
		}
		return $group;
	}
	
	public function add_to_group($journey_id)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		
		$suitable_matches=self::find_mates($journey_id)['mates'];
		if (!is_null($suitable_matches[0]))
		{

			$group = Group::where('group_id','=',$suitable_matches[0]->group_id)->first();
			$people_so_far=json_decode($group->journey_ids);
			array_push($people_so_far,$journey_id);
			/************PATH WAYPOINTS TO BE FIXED************
			***************************************************
			**************************************************/
			//try {
			Group::where('group_id','=',$group->group_id)->update(array(
				'journey_ids' => json_encode($people_so_far),
				'path_waypoints' => json_encode(self::getwaypoints($journey_id,$group->group_id)),
			));
			return intval($suitable_matches[0]->group_id);
		/*} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}*/
		}
		
		//Conditions for suitable group
		//0 is false, 1 is true
		else {
		$group = new Group;
		$group->journey_ids = json_encode(array($journey_id,));
		$group->journey_time = $journey->journey_time;
		// 0 is NO
		// 1 is YES
		// -1 is no status
		
		$group->path_waypoints = json_encode(self::getwaypoints($journey_id));
		$group->event_status = "nothing";
		try {
			$group->save();
			return $group->id;
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
		}
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
			array_push($final_path->start_order,$journey_id);
			array_push($final_path->startwaypoints,array(floatval($journey->start_lat),floatval($journey->start_long)));
			$suitable_position=0;
			$shortest_distance=10000000;
			
			$current_coordinates_start=array(floatval($journey->start_lat),floatval($journey->start_long));
			$current_coordinates_end=array(floatval($journey->end_lat),floatval($journey->end_long));
			
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
					$shortest_position=$i;
					$shortest_distance=$distance;
				}
			}
			array_splice($final_path->end_order,$shortest_position,0,$journey_id);
			array_splice($final_path->endwaypoints,$shortest_position,0,$current_coordinates_end);
			return $final_path;
		}
	}

	public function find_mates($journey_id=0,$margin_after=30)
	{
		
		/*
		$requirements = ['margin_after'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$margin_after=Input::get('margin_after');
		*/
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		
		
		// TODO : Fetch all list from the journey table with valid time time intersection
		
		$t1 = date('Y-m-d G:i:s',strtotime($journey->journey_time)-600);
		$t2 = date('Y-m-d G:i:s',strtotime($journey->journey_time)+$margin_after*60);
		$pending = Journey::where('journey_time' , '>=' , $t1 )->where('journey_time' , '<' , $t2 )->where('id','!=',intval($journey->id))->get();
		// get the userid's
		/*
		*****************HOME AND OFFICE STARTS**************************************
		*****************************************************************************
		*****************************************************************************
		$users=array();
		$strusers="(";
		for ($i=0;$i<sizeof($pending);$i++)
		{
			array_push($users,$pending[$i]->id);
			if ($i==sizeof($pending)-1)
				$strusers=$strusers.strval($users[$i]);
			else
				$strusers=$strusers.strval($users[$i]).",";
		}
		$strusers=$strusers.")";
		// TODO : Fetch all office and home jounreys from user table with valid intersection and user id is not in journey
		$t1 = date('G:i:s',strtotime($journey->journey_time));
		$t2 = date('G:i:s',strtotime($journey->journey_time)+$margin_after*60);
		$margin = date('G:i:s',strtotime("2015-12-12 00:00:00")+$margin_after*60);
		/*
		$other_users_home = DB::select(DB::raw("select * from users where id not in $strusers && leaving_home < '$t2' && leaving_home+'$margin' > '$t1'"));
		
		$other_users_office = DB::select(DB::raw("select * from users where id not in $strusers && leaving_office < '$t2' && leaving_office+'$margin' > '$t1'"));
		*****************************************************************************
		*****************************************************************************
		*****************HOME AND OFFICE ENDS****************************************
		*/
		// now you have list of journeys , with blocks already made 
		//$homeofficeusers = array_merge($other_users_home,$other_users_office);		
		$topn_weights = array();
		$corresponding_ids = array();
		$distance_threshold=2;
		$max_people=5;
		$n=5;
		for ($i=0;$i<$n;$i++)
		{
			$topn_weights[$i]=0;
			$corresponding_ids[$i]=0;
		}
		//Matching Pending Journeys
		for ($i=0;$i<sizeof($pending);$i++)
		{
			
			//$matches=0;
			//$weighted=0;
			//echo $journey->start_text . $journey->end_text . " " .$pending[$i]->start_text . $pending[$i]->end_text  . "\n";
			$matches = Graining::countMatches(json_decode($journey->path),json_decode($pending[$i]->path));
			$count1 = 0;
			foreach (json_decode($journey->path) as $key=>$value) {
        	$count1++;
    		}
    		$count2 = 0;
			foreach (json_decode($pending[$i]->path) as $key=>$value) {
        	$count2++;
    		}
			$weighted = (5*$matches - 2.5*($count1-$matches) - 2.5*($count2-$matches))/(5*$count1);
			//echo $weighted . " " . $matches . " " . $count1 . " " . $count2 . $pending[$i]->end_text . "\n";
			$distance=self::distance($journey->start_lat,$journey->start_long,$pending[$i]->start_lat,$pending[$i]->start_long);
			$people_so_far = json_decode(Group::where('group_id','=',$pending[$i]->group_id)->first()->journey_ids);

			if (sizeof($people_so_far)>=$max_people)
				continue;
			if ($distance>$distance_threshold)
				continue;
			if ($weighted>=$topn_weights[$n-1])
			{
				$topn_weights[$n-1]=$weighted;
				$corresponding_ids[$n-1]=$pending[$i]->journey_id;
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
		//Superimposing journeys liking me
		
		$final_data=array();
		for ($i=0;$i<sizeof($corresponding_ids);$i++)
		{
			$temp_id=intval($corresponding_ids[$i]);
			if ($temp_id==0)
			{
				$final_data[$i]=NULL;
				continue;
			}
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
	public function journey_edit($journey_id){

		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);

		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'journey_time' , 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'), array(), 1);
		if(is_null($path)){
			return Error::make(0,3);
		}
		$distance  = 0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time  = 0;
		foreach ($path->routes[0]->legs as $key => $value) {
			$journey_time += $value->duration->value;
		}
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

		$journey = Journey::where('id' , '=' , Input::get('user_id'))->where('journey_id' , '!=' , $journey_id)->where('journey_time' , '>' , $t2 )->where('journey_time' , '<' , $t1 )->first();
		if($this->debug > 0)
		if(!is_null($journey))
			return Error::make(1,9);
		//$json_path=self::find_path($journey->start_lat,$journey->start_long,$journey->end_lat,$journey->end_long,array(),1);
		//echo $timestamp;	
		try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'start_lat' => Input::get('start_lat'),
				'start_long' => Input::get('start_long'),
				'end_lat' => Input::get('end_lat'),
				'end_long' => Input::get('end_long'),
				'start_text' => Input::get('start_text'),
				'end_text' => Input::get('end_text'),
				'id' => Input::get('user_id'),
				'path' => json_encode(Graining::get_hashed_grid_points(json_encode($path))),
				'journey_time' => $timestamp,
				'margin_before' => Input::get('margin_before'),
				'margin_after' => Input::get('margin_after'),
				'preference' => Input::get('preference'),
				'distance' => $distance,
				'time' => $journey_time,
			));

			return Error::success("Journey Edited successfully",array('journey_id'=>intval($journey_id)));
		} catch (Exception $e) {
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