<?php
class CostCalc {
	public static function calculate($journey_id)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		$group = Group::where('group_id','=',$journey->group_id)->first();
		$event_sequence = json_decode($group->event_sequence);
		$app_distance = floatval($journey->distance_travelled_app);
		$address = "https://maps.googleapis.com/maps/api/directions/json?origin=".$journey->pickup_location."&destination=".$journey->drop_location."&waypoints=";
		$address = json_decode(file_get_contents($address));
		$google_shortest=0;
		foreach($address->routes[0]->legs as $leg)
			$google_shortest+=$leg->distance->value;
		$start_found = False;
		$address = "https://maps.googleapis.com/maps/api/directions/json?origin=".$journey->pickup_location."&destination=".$journey->drop_location."&waypoints=";
		for($i=0;$i<sizeof($event_sequence->journey_ids);$i++)
		{
			$event_seq=$event_sequence->journey_ids[$i];
			if ($start_found==True && $event_seq==$journey_id)
				break;
			if ($start_found==False && $event_seq==$journey_id)
				$start_found=True;
			if ($start_found==True)
				$address.=$event_sequence->points[$i].'|';
		}
		$address = json_decode(file_get_contents($address));
		$google_exact=0;
		foreach($address->routes[0]->legs as $leg)
			$google_exact+=$leg->distance->value;
		$distance = $app_distance*$google_shortest/($google_exact*1000);
		$fare = 35+6*$distance;
		return intval($fare);
	}

	public static function fare_estimate($journey_id)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		$start = $journey->start_lat.",".$journey->start_long;
		$end = $journey->end_lat.",".$journey->end_long;
		$address = "https://maps.googleapis.com/maps/api/directions/json?origin=".$start."&destination=".$end."&waypoints=";
		$address = json_decode(file_get_contents($address));
		$google_shortest=0;
		foreach($address->routes[0]->legs as $leg)
			$google_shortest+=$leg->distance->value;
		$fare = 35+6*($google_shortest/1000);
		return $fare;
	}
};