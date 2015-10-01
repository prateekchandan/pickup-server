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
		'10' => 'Invalid Journey Code',
		'12' => 'Invalid message code',
		'13' => 'Journey already matched',
		'14' => 'No active third user requests',
		'15' => 'Invalid third person response',
		'16' => 'user not in group!',
		'17' => 'invalid group code!',
		'18' => 'driver not in journey!',
		'19' => 'invalid driver id',
		'20' => 'this person isn\'t travelling here !!',
		'21' => 'Invalid long/lat. Check your GPS.',
		'22' => 'Group doesn\'t exist!',
		'23' => 'No routes found!',
		'30' => 'Journey already confirmed!',
		'31' => 'Invalid file uploaded!',
		'32' => 'No file received',
		'33' => 'Driver username doesn\'t exist!',
		'34' => 'Invalid password!',
		'35' => 'to_type field must have value user or driver',
		'36' => 'Journey not in Mumbai!',
		'37' => 'You cannot rate yourself!',
		'38' => 'from_type must have value user or driver',
		'39' => 'driver cannot rate another driver!',
		'40' => 'driver vacant currently',
		'41' => 'User has completed his ride!',
		'42' => 'Journey already completed!',
		'43' => 'Error calculating fare!',
		'44' => 'You can\'t cancel the ride now!',
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

		$status=200;
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