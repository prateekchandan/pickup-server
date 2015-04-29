<?php 

include 'Polyline.php';
amount_matching(file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=IIT+Bombay&destination=Kanjurmarg"),file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=IIT+Bombay&destination=Kanjurmarg"));
function amount_matching($strpath1,$strpath2) {
	
	$path1=json_decode($strpath1);
	$path2=json_decode($strpath2);
	$paths=array($path1,$path2);
	$latbounds=findBounds($paths)['lat'];
	$lngbounds=findBounds($paths)['lng'];
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
	$matches = countMatches($pathgridpoints1,$pathgridpoints2);
	$result = array();
	$result['matches'] = $matches;
	$result['extrapath1'] = sizeof($gridpoints1) - $matches;
	$result['extrapath2'] = sizeof($gridpoints2) - $matches;
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


function findBestMatches($paths)
{
	//Finding extremes using all paths
	$latbounds=findBounds($paths)['lat'];
	$lngbounds=findBounds($paths)['lng'];
	$points_set=array(); //Array consisting of of all points in all paths
	$gridpoints_set=array(); //Array consisting of gridpoints used in all paths
	$gridsizeLat=50.0;
	$gridsizeLng=50.0;
	$pathgridpoints_set=array(); //Array with hashed keys for all paths
	for ($i=0;$i<sizeof($paths);$i++)
	{
		$points_set[$i]=extractPoints($paths[$i]);
		$gridpoints_set[$i]=matchWithGrid($points_set[$i],$latbounds['top'],$lngbounds['left'],($latbounds['top']-$latbounds['bottom'])/$gridsizeLat,($lngbounds['right']-$lngbounds['left'])/$gridsizeLng);
		for ($j=0;$j<sizeof($gridpoints_set[$i]);$j++)
			$pathgridpoints_set[$i][md5($gridpoints_set[$j]['latbox'] . $gridpoints1[$j]['lngbox'])]=1;
	}
	$matches_set=array();
	for ($i=0;$i<sizeof($paths)-1;$i++)
	{
		for ($j=$i+1;$j<sizeof($paths);$j++)
		{
			$result = array();
			$result['matches'] = countMatches($pathgridpoints_set[$i],$pathgridpoints_set[$j]);
			$result['extrapath1'] = sizeof($gridpoints1) - $matches;
			$result['extrapath2'] = sizeof($gridpoints2) - $matches;
			$weightedmatch = 
			$matches_set[$i . " " . $j] = $weightedmatch;
		}
	}
}

function findBounds($paths)
{
	$latbounds=array();
	$latbounds['top']=-100.0;
	$latbounds['bottom']=100.0;
	$lngbounds['left']=200.0;
	$lngbounds['right']=-200.0;
	for ($i=0;$i<sizeof($paths);$i++)
	{
		if(floatval($paths[$i]->routes[0]->bounds->northeast->lat) >= $latbounds['top'])
			$latbounds['top']=floatval($paths[$i]->routes[0]->bounds->northeast->lat);
		if(floatval($paths[$i]->routes[0]->bounds->southwest->lat)<= $latbounds['bottom'])
			$latbounds['bottom']=floatval($paths[$i]->routes[0]->bounds->southwest->lat);
		if(floatval($paths[$i]->routes[0]->bounds->northeast->lng) >= $lngbounds['right'])
			$lngbounds['right']=floatval($paths[$i]->routes[0]->bounds->northeast->lng);
		if(floatval($paths[$i]->routes[0]->bounds->southwest->lng) <= $lngbounds['left'])
			$lngbounds['left']=floatval($paths[$i]->routes[0]->bounds->southwest->lng);
	}
	$bounds=array('lat'=>$latbounds , 'lng'=>$lngbounds);
	echo "lat bounds are " . $latbounds['top'] . " to " . $latbounds['bottom'] . "\n";
	echo "lng bounds are " . $lngbounds['left'] . " to " . $lngbounds['right'] . "\n";
	return $bounds;
}