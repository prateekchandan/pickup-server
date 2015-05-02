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

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'));
		if($path == 0){
			return Error::make(0,3);
		}
		$distance  = 0;
		foreach ($path[0]->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time  = 0;
		foreach ($path[0]->legs as $key => $value) {
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

		$journey = Journey::where('id' , '=' , Input::get('user_id'))->where('journey_time' , '>' , $t2 )->where('journey_time' , '<' , $t1 )->first();
		$flag=1;
		if(!is_null($journey)){
			if (self::distance(floatval(Input::get('start_lat')),floatval(Input::get('start_long')),$journey->start_lat,$journey->start_long)>1)
			{
				if (self::distance(floatval(Input::get('end_lat')),floatval(Input::get('end_long')),$journey->end_lat,$journey->end_long)>1)
				$flag=0;
			}		
		}
		else{
			$journey = new Journey;
		}
		
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
		if ($flag==1)
			$journey->match_status = "{\"journeys_i_like\": [] , \"journeys_liking_mine\": [] , \"matched_journeys\": [] }";

		try {
			$journey->save();
			return Error::success("Journey successfully Registered",array('journey_id'=>$journey->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	public function add_mates($journey_id)
	{
		$requirements = ['journey_ids'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		$interesting_journeys=json_decode(Input::get('journey_ids'))->ids;
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		$matchFound=0;
		$firstMatch=0;
		$secondMatch=0;
		$match_status=json_decode($journey->match_status);
		for ($i=0;$i<sizeof($interesting_journeys);$i++)
		{
			//$u1msg['type'] = 0;
			//$collection =  PushNotification::app('Pickup')
            //->to($current_journey->id)
            //->send(json_encode($u1msg));
            for ($j=0;$j<sizeof($match_status->journeys_liking_mine);$j++)
            {
            	if ($interesting_journeys[$i]==$match_status->journeys_liking_mine[$j])
            	{
            		$matchFound=1;
            		$firstMatch=intval($journey_id);
            		$secondMatch=$interesting_journeys[$i];
            	}
            }
            $current_journey = Journey::where('journey_id','=',strval($interesting_journeys[$i]))->first();
            if ($current_journey->people_needed==1)
            {
            	//SEND PUSH NOTIFICATION TO BOTH
            }
            $current_match_status=json_decode($current_journey->match_status);
            array_push($current_match_status->journeys_liking_mine,intval($journey_id));
            try {
			Journey::where('journey_id','=',$interesting_journeys[$i])->update(array(
				'match_status' => json_encode($current_match_status),
			));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
		
		}
	
		$match_status->journeys_i_like=$interesting_journeys;
		$people_needed=intval($journey->people_needed);
		if ($matchFound==1)
		{
			//SEND PUSH NOTIFICATION
			$people_needed=$people_needed-1;
			array_push($match_status->matched_journeys,$secondMatch);
			$current_journey = Journey::where('journey_id','=',strval($secondMatch))->first();
            $current_match_status=json_decode($current_journey->match_status);
            array_push($current_match_status->matched_journeys,intval($firstMatch));
            self::add_group($firstMatch,$secondMatch);
            try {
			Journey::where('journey_id','=',strval($secondMatch))->update(array(
				'match_status' => json_encode($current_match_status),
				'people_needed' => intval($current_journey->people_needed)-1,
			));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
		}
		try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'match_status' => json_encode($match_status),
				'people_needed' => $people_needed,
			));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}

		return Error::success("Mate successfully added");
	}
	public function add_groups($journey_id1=0,$journey_id2=0)
	{
		$journey1 = Journey::where('journey_id','=',$journey_id1)->first();
		if(is_null($journey1)){
			return Error::make(1,10);
		}
		$journey2 = Journey::where('journey_id','=',$journey_id2)->first();
		if(is_null($journey2)){
			return Error::make(1,10);
		}
		$group = new Group;
		$group->journey_id1 = $journey_id1;
		$group->journey_id2 = $journey_id2;
		$group->journey_id3 = 0;
		$group->user_id1 = $journey1->id;
		$group->user_id2 = $journey2->id;
		$group->user_id3 = 0;
		$best_path = self::getwaypoints($journey1,$journey2,NULL);

		try {
			$group->save();
			return Error::success("Group successfully Registered",array('group_id'=>$group->id));
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}

	}
	
	public function getwaypoints($journey1,$journey2,$journey3)
	{
		$journeys=array();
		if (is_null($journey3))
		{
			array_push($journeys,$journey1,$journey2);
			$shortest=0.0;
			$shortest_index1=0;
			$shortest_index2=0;
			$first_index_set=array(0,1);
			$second_index_set=array(1,0);
			for ($i=0;$i<2;$i++)
			{
				for ($j=0;$j<2;$j++)
				{
				$waypoints=array('first'=>array($journeys[$second_index_set[$i]]->start_lat,$journeys[$second_index_set[$i]]->start_long)
								 'second'=>array($journeys[$first_index_set[$j]]->end_lat,$journeys[$first_index_set[$j]]->end_long)	
								 );
				$test=find_path($journeys[$first_index_set[$i]]->start_lat,$journeys[$first_index_set[$i]]->start_long,
								$journeys[$second_index_set[$j]]->end_lat,$journeys[$second_index_set[$j]]->end_long,$waypoints,1)->routes[0]->legs;
				$distance=0;
				for ($k=0;$k<sizeof($test);$k++)
				{
					$distance=$distance+$tests[$k]->distance->value;
				}
				if ($distance<$shortest)
				{
					$shortest=$distance;
					$shortest_index1=$i;
					$shortest_index2=$j;
				}
				}
			}
		}
		else
		{
			array_push($journeys,$journey_id1,$journey_id2,$journey_id3);
			$shortest=0.0;
			$shortest_index1=0;
			$shortest_index2=0;
			$first_index_set=array(0,0,1,1,2,2);
			$second_index_set=array(1,2,0,2,0,1);
			$third_index_set=array(2,1,2,0,1,0);
			
			for ($i=0;$i<6;$i++)
			{
				for ($j=0;$j<6;$j++)
				{
				$waypoints=array('first'=>array($journeys[$second_index_set[$i]]->start_lat,$journeys[$second_index_set[$i]]->start_long)
								 'second'=>array($journeys[$third_index_set[$i]]->end_lat,$journeys[$third_index_set[$i]]->end_long)	
								 'third'=>array($journeys[$first_index_set[$j]]->start_lat,$journeys[$first_index_set[$j]]->start_long)
								 'fourth'=>array($journeys[$second_index_set[$j]]->end_lat,$journeys[$second_index_set[$j]]->end_long)	
								 );
				$test=find_path($journeys[$first_index_set[$i]]->start_lat,$journeys[$first_index_set[$i]]->start_long,
								$journeys[$third_index_set[$j]]->end_lat,$journeys[$third_index_set[$j]]->end_long,$waypoints,1)->routes[0]->legs;
				$distance=0;
				for ($k=0;$k<sizeof($test);$k++)
				{
					$distance=$distance+$tests[$k]->distance->value;
				}
				if ($distance<$shortest)
				{
					$shortest=$distance;
					$shortest_index1=$i;
					$shortest_index2=$j;
				}
				}
			}
		}


	}

	public function find_mates($journey_id=0)
	{
		$requirements = ['margin_after'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}
		
		if ($journey->people_needed<2)
		{
			return Error::make(1,13);
		}
		// TODO : Fetch all list from the journey table with valid time time intersection
		
		$t1 = date('Y-m-d G:i:s',strtotime($journey->journey_time));
		$t2 = date('Y-m-d G:i:s',strtotime($journey->journey_time)+floatval(Input::get('margin_after'))*60);
		$pending = Journey::where('journey_time' , '>=' , $t1 )->where('journey_time' , '<' , $t2 )->where('people_needed' , '>' , 0 )->where('journey_id','!=',$journey_id)->get();
		// get the userid's
		//echo $pending;
		$users=array();
		//array_push($users, 0);
		$strusers="(";
		for ($i=0;$i<sizeof($pending);$i++)
		{
			array_push($users,$pending[$i]->id);
			if ($i==sizeof($pending)-1)
				$strusers=$strusers.strval($users[$i]);
			else
				$strusers=$strusers+strval($users[$i])+",";
		}
		$strusers=$strusers.")";
		// TODO : Fetch all office and home jounreys from user table with valid intersection and user id is not in journey
		$t1 = date('G:i:s',strtotime($journey->journey_time));
		$t2 = date('G:i:s',strtotime($journey->journey_time)+floatval(Input::get('margin_after'))*60);
		$margin = date('G:i:s',strtotime("2015-12-12 00:00:00")+floatval(Input::get('margin_after'))*60);
		/*
		$other_users_home = DB::select(DB::raw("select * from users where id not in $strusers && leaving_home < '$t2' && leaving_home+'$margin' > '$t1'"));
		
		$other_users_office = DB::select(DB::raw("select * from users where id not in $strusers && leaving_office < '$t2' && leaving_office+'$margin' > '$t1'"));
		*/
		// now you have list of journeys , with blocks already made 
		//$homeofficeusers = array_merge($other_users_home,$other_users_office);		
		$topn_weights = array();
		$corresponding_ids = array();
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
			$matches = Graining::countMatches(json_decode($journey->path),json_decode($pending[$i]->path));
			$weighted = 5*$matches - 2.5*sizeof(json_decode($journey->path)) - 2.5*sizeof(json_decode($pending[$i]->path));
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
		$people_liking_me = json_decode($journey->match_status)->journeys_liking_mine;
		for ($i=0;$i<sizeof($people_liking_me);$i++)
		{
			$isAlreadyPresent=0;
			for ($j=0;$j<$n;$j++)
			{
				if ($people_liking_me[$i]==$corresponding_ids[$j])
				{
					$isAlreadyPresent=1;
					array_splice($corresponding_ids,$j,1);
					array_splice($corresponding_ids,0,0,$people_liking_me[$i]);
				}
			}
			if ($isAlreadyPresent==0)
				{
					array_splice($corresponding_ids,$n-1,1);
					array_splice($corresponding_ids,0,0,$people_liking_me[$i]);
				}
		}
		$final_data=array();
		for ($i=0;$i<sizeof($corresponding_ids);$i++)
			$final_data[$i]=intval($corresponding_ids[$i]);
		return $final_data;
		
	}

	public function match_third($journey_id)
	{
		$requirements = ['id_to_include'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey)){
			return Error::make(1,10);
		}


	}
	public function journey_edit($journey_id){

		$journey = Journey::where('journey_id','=',$journey_id)->first();
		if(is_null($journey))
			return Error::make(1,11);

		$requirements = ['start_lat' , 'start_long','end_lat' , 'end_long' , 'user_id' , 'journey_time' , 'margin_after' , 'margin_before' , 'preference' , 'start_text' , 'end_text'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);

		$path = $this->find_path(Input::get('start_lat') , Input::get('start_long') , Input::get('end_lat') , Input::get('end_long'));
		if($path == 0){
			return Error::make(0,3);
		}
		$distance  = 0;
		foreach ($path[0]->legs as $key => $value) {
			$distance += $value->distance->value;
		}
		$journey_time  = 0;
		foreach ($path[0]->legs as $key => $value) {
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

		try {
			Journey::where('journey_id','=',$journey_id)->update(array(
				'start_lat' => Input::get('start_lat'),
				'start_long' => Input::get('start_long'),
				'end_lat' => Input::get('end_lat'),
				'end_long' => Input::get('end_long'),
				'start_text' => Input::get('start_text'),
				'end_text' => Input::get('end_text'),
				'id' => Input::get('user_id'),
				'journey_time' => $timestamp,
				'margin_before' => Input::get('margin_before'),
				'margin_after' => Input::get('margin_after'),
				'preference' => Input::get('preference'),
				'distance' => $distance,
				'time' => $journey_time,
			));

			return Error::success("Journey successfully Edited");
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}

	public function journey_delete($journey_id){
		$journey = Journey::where('journey_id','=',$journey_id)->delete();
		return Error::success("Journey successfully Deleted");
	}
	
	function MakeGroups(){
		
		$t1 = date('Y-m-d G:i:s',time());
		$t2 = date('Y-m-d G:i:s',time()+600);
		$pending = Journey::where('journey_time' , '>' , $t1 )->where('journey_time' , '<' , $t2 )->get();
		
		$l = sizeof($pending);
		for ($i=0; $i < $l; $i++) { 
			$pending[$i]->group = 0;
		}
		$groups = array();
		for ($i=0; $i < $l; $i++) { 
			$mind = 99999999;
			$mini = $i;
			$path = $this->find_path($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$i]->end_lat , $pending[$i]->end_long);
			if($pending[$i]->group == 1)
				continue;

			for ($j=$i+1; $j < $l; $j++) {
				
				if($pending[$i]->id == $pending[$j]->id)
					continue;
				if($this->distance($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$j]->start_lat , $pending[$j]->start_long) > 3)
					continue;
				if($this->distance($pending[$i]->end_lat , $pending[$i]->end_long , $pending[$j]->end_lat , $pending[$j]->end_long) > 3)
					continue;
				
				$timediff = abs(strtotime($pending[$i]->journey_time) - strtotime($pending[$j]->journey_time)) / (60*60);
				if($timediff > 1)
					continue;

				$p = array();
				$p[0] = $this->find_path($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$i]->end_lat , $pending[$i]->end_long , 
					array(
						array($pending[$j]->start_lat,$pending[$j]->start_long),
						array($pending[$j]->end_lat,$pending[$j]->end_long)
						)
					);
				$p[1] = $this->find_path( $pending[$j]->start_lat,$pending[$j]->start_long, $pending[$i]->end_lat , $pending[$i]->end_long , 
					array(
						array($pending[$i]->start_lat , $pending[$i]->start_long),
						array($pending[$j]->end_lat,$pending[$j]->end_long)
						)
					);
				$p[2] = $this->find_path($pending[$i]->start_lat , $pending[$i]->start_long , $pending[$j]->end_lat,$pending[$j]->end_long, 
					array(
						array($pending[$j]->start_lat,$pending[$j]->start_long),
						array($pending[$i]->end_lat , $pending[$i]->end_long )
							)
					);
				$p[3] = $this->find_path( $pending[$j]->start_lat,$pending[$j]->start_long, $pending[$j]->end_lat , $pending[$j]->end_long , 
					array(
						array($pending[$i]->start_lat , $pending[$i]->start_long),
						array($pending[$i]->end_lat , $pending[$i]->end_long )
						)
					);
				$d = array();
				for ($k=0; $k < 3; $k++) { 
					if($p[$k]==0)
						$p[$k] = json_decode('[{"legs" : [{"distance" : {"value" : 9999999999}}]}]');
				
					$p[$k] = $p[$k][0];
					$d[$k] = 0;
					foreach ($p[$k]->legs as $key => $leg) {
						$d[$k] += $leg->distance->value;
					}
				}
				$mi = 0; $md = $d[0];
				for ($k=0; $k < 3; $k++) { 
					if($d[$k] < $d[$mi]){
						$mi = $k;
						$md = $d[$k];
					}
				}

				if($md < $mind){
					$mind = $md;
					$mini = $j;
					$path = $p[$mi];
				}
			}
			array_push($groups, array($pending[$i]->id , $pending[$mini]->id , $path , $pending[$i]->journey_id, $pending[$mini]->journey_id));
			$pending[$i]->group =1;
			$pending[$mini]->group =1;
		}
		foreach ($groups as $key => $group) {
			$u1 = User::where('id','=',$group[0])->first() ;
			$u2 = User::where('id','=',$group[1])->first() ;
			$path = $group[2];
			$jpair = new FinalJourney;
			$jpair->u1 = $group[0];
			$jpair->u2 = $group[1];
			$jpair->j1 = $group[3];
			$jpair->j2 = $group[4];
			$j1 = Journey::where('journey_id' , '=' , $group[3])->first();
			$j2 = Journey::where('journey_id' , '=' , $group[4])->first();
			$events = array();
			
			if($group[0] == $group[1]){
				$path = $path[0];
				$distance = $path->legs[0]->distance->value;
				$time = $path->legs[0]->duration->value;
				$jpair->u1_distance = $distance;
				$jpair->u2_distance = $distance;
				$jpair->u1_time = $time;
				$jpair->u2_time = $time;
				$events['accept'] = array($group[0]=>0 );
				$events['reject'] = array($group[0]=>0 );
				$events['start_ride'] = array($group[0]=>0 );
				$events['end_ride'] = array($group[0]=>0 );
			}
			else{
				$jpair->u1_distance = $path->legs[1]->distance->value;;
				$jpair->u2_distance = $path->legs[1]->distance->value;;
				$jpair->u1_time = $path->legs[1]->duration->value;
				$jpair->u2_time = $path->legs[1]->duration->value;
				//echo json_encode($path);

				$d1 = $this->distance($path->legs[0]->start_location->lat , $path->legs[0]->start_location->lng , $j1->start_lat ,  $j1->start_long);
				$d2 = $this->distance($path->legs[0]->start_location->lat , $path->legs[0]->start_location->lng , $j2->start_lat ,  $j2->start_long);
				 
				if($d1 < $d2){
					$jpair->u1_distance += $path->legs[0]->distance->value;
					$jpair->u1_time += $path->legs[0]->duration->value;
				}
				else{
					$jpair->u2_distance += $path->legs[0]->distance->value;
					$jpair->u2_time += $path->legs[0]->duration->value;
				}

				$d1 = $this->distance($path->legs[0]->end_location->lat , $path->legs[0]->end_location->lng , $j1->end_lat ,  $j1->end_long);
				$d2 = $this->distance($path->legs[0]->end_location->lat , $path->legs[0]->end_location->lng , $j2->end_lat ,  $j2->end_long);
				 

				if($d1 < $d2){
					$jpair->u1_distance += $path->legs[2]->distance->value;
					$jpair->u1_time += $path->legs[2]->duration->value;
				}
				else{
					$jpair->u2_distance += $path->legs[2]->distance->value;
					$jpair->u2_time += $path->legs[2]->duration->value;
				}
				$events['accept'] = array($group[0]=>0 , $group[1]=>0 );
				$events['reject'] = array($group[0]=>0 , $group[1]=>0 );
				$events['start_ride'] = array($group[0]=>0 , $group[1]=>0 );
				$events['end_ride'] = array($group[0]=>0 , $group[1]=>0 );
			}
			$jpair->event_status = json_encode($events);
			$jpair->path = json_encode($path);
			$jpair->save();
			$u1msg = array();
			$u1msg['journey_id'] = $jpair->id;
			$u1msg['name'] = $u2->first_name;
			if($group[0]==$group[1]){
				$u1msg['type'] = 0;
				$collection =  PushNotification::app('Pickup')
                ->to($u1->registration_id)
                ->send(json_encode($u1msg));
			}
			else{
				$u1msg['type'] = 1;
				$u1msg['name'] = $u2->first_name;
				$collection = PushNotification::app('Pickup')
	                ->to($u1->registration_id)
	                ->send(json_encode($u1msg));
	            $u1msg['name'] = $u1->first_name;
				$collection1 = PushNotification::app('Pickup')
	                ->to($u2->registration_id)
	                ->send(json_encode($u1msg));
            }
            foreach ($collection->pushManager as $push) {
		    	$response = $push->getAdapter()->getResponse();
		    	print_r($response);
			}
			if(isset($collection1)){
				foreach ($collection1->pushManager as $push) {
		    	$response = $push->getAdapter()->getResponse();
		    	print_r($response);
				}
			}
		}
		
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
