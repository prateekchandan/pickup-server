<?php 

include 'Polyline.php';
amount_matching(file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=IIT+Bombay&destination=Kanjurmarg"),file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=IIT+Bombay&destination=Kanjurmarg"));
function amount_matching($strpath1,$strpath2) {
	
	$path1=json_decode($strpath1);
	$path2=json_decode($strpath2);
	$latbounds=array();
	if (floatval($path1->routes[0]->bounds->northeast->lat)>=floatval($path2->routes[0]->bounds->northeast->lat))
		$latbounds['top']=floatval($path1->routes[0]->bounds->northeast->lat);
	else
		$latbounds['top']=floatval($path2->routes[0]->bounds->northeast->lat);

	if (floatval($path1->routes[0]->bounds->southwest->lat)<=floatval($path2->routes[0]->bounds->southwest->lat))
		$latbounds['bottom']=floatval($path1->routes[0]->bounds->southwest->lat);
	else
		$latbounds['bottom']=floatval($path2->routes[0]->bounds->southwest->lat);

	$lngbounds=array();
	if (floatval($path1->routes[0]->bounds->northeast->lng)>=floatval($path2->routes[0]->bounds->northeast->lng))
		$lngbounds['right']=floatval($path1->routes[0]->bounds->northeast->lng);
	else
		$lngbounds['right']=floatval($path2->routes[0]->bounds->northeast->lng);

	if (floatval($path1->routes[0]->bounds->southwest->lng)<=floatval($path2->routes[0]->bounds->southwest->lng))
		$lngbounds['left']=floatval($path1->routes[0]->bounds->southwest->lng);
	else
		$lngbounds['left']=floatval($path2->routes[0]->bounds->southwest->lng);
	
	$points1=extractPoints($path1);
	$points2=extractPoints($path2);	
	$gridsizeLat=50.0;
	$gridsizeLng=50.0;
	
	$gridpoints1=matchWithGrid($points1,$latbounds['top'],$lngbounds['left'],($latbounds['top']-$latbounds['bottom'])/$gridsizeLat,($lngbounds['right']-$lngbounds['left'])/$gridsizeLng);
	$gridpoints2=matchWithGrid($points2,$latbounds['top'],$lngbounds['left'],($latbounds['top']-$latbounds['bottom'])/$gridsizeLat,($lngbounds['right']-$lngbounds['left'])/$gridsizeLng);
	$pathgridpoints1=array();
	$pathgridpoints2=array();
	for ($i=0;$i<sizeof($gridpoints1);$i++)
		$pathgridpoints1[md5($gridpoints1[$i]['latbox'] . $gridpoints1[$i]['lngbox'])]=1;
	for ($i=0;$i<sizeof($gridpoints2);$i++)
		$pathgridpoints2[md5($gridpoints2[$i]['latbox'] . $gridpoints2[$i]['lngbox'])]=1;
	$matches = countMatches($pathgridpoints1,$pathgridpoints2)
	$result = array();
	$result['matches'] = $matches;
	$result['extrapath1'] = sizeof(gridpoints1) - $matches;
	$result['extrapath2'] = sizeof(gridpoints2) - $matches;
	return $result;
}
function distance($lat1, $lon1, $lat2, $lon2, $unit = "K") {
	 
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

function addPoints($start,$end,$n)
{
	$added_points=array();
	for ($i=0;$i<$n;$i++)
	{
		array_push($added_points,array('lat' => (($start['lat']*($n-$i) + $end['lat']*($i+1)) / ($n+1)) , 'lng' => (($start['lng']*($n-$i) + $end['lng']*($i+1)) / ($n+1))));
	}
	return $added_points;
}

function extractPoints($path1)
{
	$threshold=0.1;
	$points=array();
	array_push($points,array('lat' => $path1->routes[0]->legs[0]->start_location->lat , 'lng' => $path1->routes[0]->legs[0]->start_location->lng));
	//$points = Polyline::Decode($path1->routes[0]->legs[0]->steps[0]->polyline->points);
	for ($i=0;$i<sizeof($path1->routes[0]->legs);$i++)
	{
		for ($j=0;$j<sizeof($path1->routes[0]->legs[$i]->steps);$j++)
		{
			$points_in_this_step=Polyline::Decode($path1->routes[0]->legs[$i]->steps[$j]->polyline->points);
			for ($k=2;$k<sizeof($points_in_this_step);$k+=2)
			{
				array_push($points,array('lat' => $points_in_this_step[$k] , 'lng' => $points_in_this_step[$k+1]));
			}
		}
	}
	
	for ($i=0;$i<sizeof($points)-1;$i++) {	
		$dist=distance($points[$i]['lat'],$points[$i]['lng'],$points[$i+1]['lat'],$points[$i+1]['lng']);
		if ($dist>$threshold)
		{
			$numPoints=round($dist/$threshold);
			$points_to_be_added = addPoints($points[$i],$points[$i+1],$numPoints);
			array_splice($points, $i+1, 0, $points_to_be_added);
		}	
	}
	
	return $points;
}
function matchWithGrid($points,$top,$left,$lat_interval=0.001,$lng_interval=0.001)
{
	
	$gridpoints=array();
	for ($i=0;$i<sizeof($points);$i++)
	{
		array_push($gridpoints,array('latbox'=> round(($top-$points[$i]['lat'])/$lat_interval) , 'lngbox' => round(($points[$i]['lng']-$left)/$lng_interval) ));
	}
	return $gridpoints;
}

function countMatches($path1,$path2)
{
	$count=0;
	foreach($path1 as $x => $x_value) {
    if (array_key_exists($x, $path2))
		$count++;
	}
	return $count;
}