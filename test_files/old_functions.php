<?php
//OLD FUNCTIONS
//HomeController
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