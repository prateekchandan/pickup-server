<?php

class Graining{
/*	
public static function amount_matching($strpath1,$strpath2) {
	$path1=json_decode($strpath1);
	$path2=json_decode($strpath2);
	$paths=array($path1,$path2);
	$points1=self::extractPoints($path1);
	$points2=self::extractPoints($path2);	
	$gridpoints1=self::matchWithGrid($points1);
	$gridpoints2=self::matchWithGrid($points2);
	$pathgridpoints1=array();
	$pathgridpoints2=array();
	for ($i=0;$i<sizeof($gridpoints1);$i++)
		$pathgridpoints1[md5($gridpoints1[$i]['lat'] . $gridpoints1[$i]['lng'])]=1;
	for ($i=0;$i<sizeof($gridpoints2);$i++)
		$pathgridpoints2[md5($gridpoints2[$i]['lat'] . $gridpoints2[$i]['lng'])]=1;
	$matches = self::countMatches($pathgridpoints1,$pathgridpoints2);
	$result = array();
	$result['matches'] = $matches;
	$result['extrapath1'] = sizeof($gridpoints1) - $matches;
	$result['extrapath2'] = sizeof($gridpoints2) - $matches;
	$matching_amout = 5*$result['matches'] - 2.5*$result['extrapath1'] - 2.5*$result['extrapath2'];
	return $matching_amout;
}
*/
public static function get_hashed_grid_points($strpath1)
{
	$path1=json_decode($strpath1);
	$points1=self::extractPoints($path1);
	$gridpoints1=self::matchWithGrid($points1);
	$pathgridpoints1=array();
	for ($i=0;$i<sizeof($gridpoints1);$i++)
		$pathgridpoints1[md5($gridpoints1[$i]['lat'] . $gridpoints1[$i]['lng'])]=1;
	return $pathgridpoints1;
}
public static function distance($lat1, $lon1, $lat2, $lon2, $unit = "K") {
	 
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

public static function addPoints($start,$end,$n)
{
	$added_points=array();
	for ($i=0;$i<$n;$i++)
	{
		array_push($added_points,array('lat' => (($start['lat']*($n-$i) + $end['lat']*($i+1)) / ($n+1)) , 'lng' => (($start['lng']*($n-$i) + $end['lng']*($i+1)) / ($n+1))));
	}
	return $added_points;
}

public static function extractPoints($path1)
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
		$dist=self::distance($points[$i]['lat'],$points[$i]['lng'],$points[$i+1]['lat'],$points[$i+1]['lng']);
		if ($dist>$threshold)
		{
			$numPoints=round($dist/$threshold);
			$points_to_be_added = self::addPoints($points[$i],$points[$i+1],$numPoints);
			array_splice($points, $i+1, 0, $points_to_be_added);
		}	
	}
	
	return $points;
}
public static function matchWithGrid($points)
{	
	$gridpoints=array();
	for ($i=0;$i<sizeof($points);$i++)
	{
		$newlat=round($points[$i]['lat']*10000);
		if ($newlat%2==1)
			$newlat=$newlat-1;
		$newlng=round($points[$i]['lng']*10000);
		if ($newlng%2==1)
			$newlng=$newlng-1;
		$newlat=$newlat/10000;
		$newlng=$newlng/10000;
		array_push($gridpoints,array('lat'=> $newlat , 'lng'=> $newlng));
	}
	return $gridpoints;
}


public static function countMatches($path1,$path2)
{
	$count=0;
	foreach($path1 as $x => $x_value) {
    if (array_key_exists($x, $path2))
		$count++;
	}
	return $count;
}

/*
public function findBestMatches($paths,$matches_needed)
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
	$person_matched=array();

	//$matches_set_key=array();
	for ($i=0;$i<sizeof($paths);$i++)
	{
		$matches_set[$i]=array();
		$person_matched[$i]=array();
		for ($j=0;$j<sizeof($paths);$j++)
		{
			$matches_set[$i][$j]=0;
			$person_matched[$i][$j]=0;
		}
	}
	for ($i=0;$i<sizeof($paths)-1;$i++)
	{
		for ($j=$i+1;$j<sizeof($paths);$j++)
		{
			$result = array();
			$result['matches'] = countMatches($pathgridpoints_set[$i],$pathgridpoints_set[$j]);
			$result['extrapath1'] = sizeof($gridpoints1) - $matches;
			$result['extrapath2'] = sizeof($gridpoints2) - $matches;
			$weightedmatch = 5*$result['matches'] - 2.5*$result['extrapath1'] - 2.5*$result['extrapath2'];
			//$matches_set[$i . " " . $j] = $weightedmatch;
			//array_push($matches_set,$weightedmatch);
			//array_push($matches_set_key,$i . " " . $j);
			$matches_set[$i][$j]=$weightedmatch;
		}
	}
	for ($i=0;$i<sizeof($paths)-1;$i++)
	{
		for ($j=$i+1;$j<sizeof($paths);$j++)
			$matches_set[$j][$i]=$matches_set[$i][$j];
	}
	$best_weight=0.0;
	$best_index=0;
	$user_to_be_matched=0;
	$matches=array();
	for ($i=0;$i<sizeof($paths);$i++)
		$matches++;
	while ($user_to_be_matched!=sizeof($paths) && $total_matches!=$matches_needed)
	{
		while ($person_matched[$user_to_be_matched][0]!=0) $user_to_be_matched++;
	for ($i=0;$i<sizeof($paths);$i++)
	{
		if ($matches_set[$user_to_be_matched][$i]>$best_weight && $person_matched[$user_to_be_matched][$i]==0)
		{
			$best_weight=$matches_set[$user_to_be_matched][$i];
			$best_index=$i;
		}
	}
	for ($i=0;$i<sizeof($paths);$i++)
	{
		$person_matched[$user_to_be_matched][$i]=1;
		$person_matched[$i][$user_to_be_matched]=1;
		$person_matched[$best_index][$i]=1;
		$person_matched[$i][$best_index]=1;
	}
	$matches[$user_to_be_matched]=$best_index;
	$matches[$best_index]=$user_to_be_matched;
	$total_matches++;
	$user_to_be_matched++;

	}
	return $matches;
	

}*/
/*
public function findBounds($paths)
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
}*/
};
