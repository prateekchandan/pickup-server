<?php
class CostCalc{
	public static function calc($distance,$time=0,$type=0){
		if($type==0){
			//return 150 + max(array(0,$distance-4000)) * (21/1000);
			return min(array(150,$distance/1000.0 * 21));
		}
	}
};