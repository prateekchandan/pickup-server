<?php

class BaseController extends Controller {

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	private $privatekey = "PickupMailCheckingYo!";
	public function check_requirements($requirements){

		foreach ($requirements as $value) {
			if(!Input::has($value))
				return $value;
		}
		return false;
	}
	
	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}

	public function encrypt($string)
	{		
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->privatekey), $string, MCRYPT_MODE_CBC, md5(md5($this->privatekey))));
	}
	public function decrypt($encrypted)
	{
		return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->privatekey), base64_decode($encrypted), MCRYPT_MODE_CBC, md5(md5($this->privatekey))), "\0");
		
	}
	public function log_matches($data)
	{
	$file = fopen("/root/match_logs.txt",'a');
	fwrite($file,$data);
	if($data != ""){
        $t = date("Y-m-d G:i:s",time());
        $data ="\nFound matches at at ".$t."\n----------------------------------\n\n";
	}
	fwrite($file,$data);

	}
	public function log_data($data)
	{
	$file = fopen("/root/cronlog.txt",'a');
	fwrite($file,$data);
	if($data != ""){
        $t = date("Y-m-d G:i:s",time());
        $data ="\nPinged the server now at ".$t."\n----------------------------------\n\n";
	}
	fwrite($file,$data);
	}
	public function rating_edit($rating_id,$rating_amount)
	{
		$rating = Rating::where('rating_id','=',$rating_id)->first();
		if (strcmp($rating->to_type, "driver")==0)
		{
			$driver = Driver::where('driver_id','=',$rating->to_id)->first();
			if (is_null($driver))
				return Error::make(1,19);
			$driver_rating = floatval($driver->rating);
			$number_rating = intval($driver->number_rating);
			$driver_rating = ($driver_rating*$number_rating-$rating->rating+$rating_amount) / ($number_rating);
			try {
			Driver::where('driver_id','=',$driver->driver_id)->update(array(
				'number_rating' => intval($number_rating),
				'rating' => $driver_rating,
				));
			} 
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}
		else if (strcmp($rating->to_type, "user")==0)
		{
			$user = User::where('id','=',$rating->to_id)->first();
			if (is_null($user))
				return Error::make(1,1);
			$user_rating = floatval($user->rating);
			$number_rating = intval($user->number_rating);
			$user_rating = ($user_rating*$number_rating-$rating->rating+$rating_amount) / ($number_rating);
			try {
			User::where('id','=',$user->id)->update(array(
				'number_rating' => intval($number_rating),
				'rating' => $user_rating,
				));
			} 
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}
		try {
				Rating::where('rating_id','=',$rating_id)->update(array(
					'rating' => intval($rating_amount),
					));
				return Error::success("Rating saved successfully!" , array());
			}
			catch(Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
	}
	public function add_rating()
	{
		$requirements = ['from_type','to_type','from_id','to_id','rating'];
		$check  = self::check_requirements($requirements);
		if($check)
			return Error::make(0,100,$check);
		if (strcmp(Input::get('from_type'),Input::get('to_type'))==0 && strcmp(Input::get('from_id'),Input::get('to_id'))==0)
			return Error::make(1,37);
		else if (strcmp(Input::get('from_type'),'driver')==0 && strcmp(Input::get('to_type'),'driver')==0)
			return Error::make(1,39);
		//Check from type
		if (strcmp(Input::get('from_type'), 'driver')==0)
		{
			$check_driver = Driver::where('driver_id','=',Input::get('from_id'))->first();
			if (is_null($check_driver))
				return Error::make(1,19);
		}
		else if (strcmp(Input::get('from_type'), 'user')==0)
		{
			$check_user = User::where('id','=',Input::get('from_id'))->first();
			if (is_null($check_user))
				return Error::make(1,1);
		}
		else
			return Error::make(1,38);
		$old_rating = Rating::where('from_type','=',Input::get('from_type'))->
							  where('to_type','=',Input::get('to_type'))->
							  where('from_id','=',Input::get('from_id'))->
							  where('to_id','=',Input::get('to_id'))->first();
		if (!is_null($old_rating))
			return self::rating_edit($old_rating->rating_id,intval(Input::get('rating')));
		$rating = new Rating;
		//$driver->group_id = Input::get('group_id');
		$rating->from_type=Input::get('from_type');
		$rating->to_type=Input::get('to_type');
		$rating->from_id = intval(Input::get('from_id'));
		$rating->to_id = intval(Input::get('to_id'));
		$rating->rating = intval(Input::get('rating'));
		if (strcmp($rating->to_type, "driver")==0)
		{
			$driver = Driver::where('driver_id','=',$rating->to_id)->first();
			if (is_null($driver))
				return Error::make(1,19);
			$driver_rating = floatval($driver->rating);
			$number_rating = intval($driver->number_rating);
			$driver_rating = ($driver_rating*$number_rating+$rating->rating) / ($number_rating+1);
			$number_rating = $number_rating+1;
			try {
			Driver::where('driver_id','=',$driver->driver_id)->update(array(
				'number_rating' => intval($number_rating),
				'rating' => $driver_rating,
				));
			} 
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}
		else if (strcmp($rating->to_type, "user")==0)
		{
			$user = User::where('id','=',$rating->to_id)->first();
			if (is_null($user))
				return Error::make(1,1);
			$user_rating = floatval($user->rating);
			$number_rating = intval($user->number_rating);
			$user_rating = ($user_rating*$number_rating+$rating->rating) / ($number_rating+1);
			$number_rating = $number_rating+1;
			try {
			User::where('id','=',$user->id)->update(array(
				'number_rating' => intval($number_rating),
				'rating' => $user_rating,
				));
			} 
			catch (Exception $e) {
				return Error::make(101,101,$e->getMessage());
			}
		}
		else
		{
			return Error::make(1,35);
		}
		try {
			$rating->save();
			return Error::success("Rating saved successfully!" , array());
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function push_test($journey_id)
	{
		$requirements = ['msgcode'];
		$check  = self::check_requirements($requirements);
		$data = array();
		switch(intval(Input::get('msgcode')))
		{
			case 10:
			$data = array('user_id'=>1,'user_name'=>'Meet Udeshi','fbid'=>'11323');
			break;
			case 13:
			case 15:
			case 14:
			$data = array('user_id'=>1,'user_name'=>'Meet Udeshi');
			break;
			case 11:
			case 12:
			$data = array('driver_id'=>1);
		}
		if($check)
		return Error::make(0,100,$check);
		self::send_push(array($journey_id),intval(Input::get('msgcode')),$data);
	}
	public function send_push($journey_ids,$msgcode,$data)
	{
		$message_data = array(
								13=>"A User cancelled his journey!",
								10=>"A new user has just joined!",
								12=>"Driver is reaching you..",
								11=>"Driver allocated!",
								14=>"Picked up person",
								15=>"Person just finished ride!",
				);
		$message = $message_data[$msgcode];
		$data['time']=time();
		$data['journey_id']=0;
		foreach ($journey_ids as $journey_id1) {
				$data['journey_id']=$journey_id1;
				$journey_details = Journey::where('journey_id','=',$journey_id1)->first();
				$user = User::where('id' , '=',intval($journey_details->id))->first();
				$uMsg = array();
				$uMsg['type'] = $msgcode;
				$uMsg['data'] = $data;
				$uMsg['message'] = $message;
				try {
				//Notifying all existing users about new guy
				$collection = PushNotification::app('Pickup')
	            	->to($user->registration_id)
	            	->send(json_encode($uMsg));
	            foreach ($collection->pushManager as $push) {
    			$success = $push->getAdapter()->getResponse();
				}
	            $log_data = print_r($success,true);
	            	self::log_data($log_data);
	            }
	            catch (Exception $e)
	            {
	            	self::log_data(json_encode(Error::make(101,101,$e->getMessage())));
	            }
	            
			}
	}


	

}
