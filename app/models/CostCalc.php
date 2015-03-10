<?php
class CostCalc{
	public static calc($distance,$time=0,$type=0){
		if($type==0){
			return 150 + max(array(0,$distance-4000)) * 10;
		}
	}
};