
	$count=0;
	foreach($path1 as $x => $x_value) {
    if (array_key_exists($x, $path2))
		$count++;
	}
	return $count;