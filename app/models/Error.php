<?php
class Error{

	private static $error_messages =  array(
		#error code to error message mapping
		'0' => "Some Error Occured",
		'401' => "Authentication Failed",
		'2' => "Authentication key Required",
		'404' => "404 Error : URL Not Found",
		'100' => "Input field required : " ,
		'101' => "" ,
		'1'  => "Invalid user_id",
		'11'  => "Invalid journey_id",
		'3' => "Invalid Coordinates , path doesn't exists",
		'4' => "Journey beyond 100km unsupported",
		'5' => "Invalid Datetime",
		'6' => "Journey in past not allowed",
		'7' => "Error margin should be in limits of 1 hour",
		'8' => "Invalid Preference",
		'9' => "Two Journey cant be done within 3 hrs",
		'10' => 'Invalid Journey Code'
		);

	// Error type
	public static function make($type=0 , $code = 0 , $field="")
	{
		$message=self::$error_messages[$code];

		if($code == 100 || $code == 101)
			$message.=$field;

		$contents= array('error' => 1, 'message' => $message);

		if($type >= 110)
			$status = $type;
		else
			$status = 412;

		$response = Response::make($contents, $status,array('statusText'=>$message));
		return $response;
	}

	public static function success($message="Success",$data= array())
	{
		$contents= array('error' => 0, 'message' => $message);
		$contents=array_merge($contents,$data);
		$status = 200;

		$response = Response::make($contents, $status,array('statusText'=>$message));
		return $response;
	}
	
}