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
	$file = fopen("/home/kalpesh/cronlog.txt",'a');
	fwrite($file,$data);
	if($data != ""){
        $t = date("Y-m-d G:i:s",time());
        $data ="\nPinged the server now at ".$t."\n----------------------------------\n\n";
	}
	fwrite($file,$data);
	}
	public function get_pending_events($journey_id,$event_ids)
	{
		$events_received = json_decode($event_ids);
		foreach ($events_received as $event_id) {
			$event = PendingEvent::where('event_id','=',$event_id)->first();
			if (!is_null($event) && intval($event->journey_id)==$journey_id)
			{
				PendingEvent::where('event_id','=',$event_id)->delete();
			}
		}
		$remaining_events = PendingEvent::where('journey_id','=',$journey_id)->get();
		foreach ($remaining_events as $event) {
			PendingEvent::where('event_id','=',$event->event_id)->delete();
		}
		return $remaining_events;
		//return Error::success('Remaining events',array('remaining_events'=>$remaining_events));
	}
	public function driver_get_pending_events($driver_id,$event_ids)
	{
		$events_received = json_decode($event_ids);
		foreach ($events_received as $event_id) {
			$event = DriverEvent::where('event_id','=',$event_id)->first();
			if (!is_null($event) && intval($event->driver_id)==$driver_id)
			{
				DriverEvent::where('event_id','=',$event_id)->delete();
			}
		}
		$remaining_events = DriverEvent::where('driver_id','=',$driver_id)->get();
		foreach ($remaining_events as $event) {
			DriverEvent::where('event_id','=',$event->event_id)->delete();
		}
		return $remaining_events;
		//return Error::success('Remaining events',array('remaining_events'=>$remaining_events));
	}
	public function push_test($journey_id)
	{
		$requirements = ['msgcode'];
		$check  = self::check_requirements($requirements);
		$data = array();
		$isdriver = 0;
		$group_id = Group::first();
		
		if(is_null($group_id))
			$group_id = 1;
		else
			$group_id = $group_id->group_id;

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
			break;
			case 16:
				$isdriver = 1;
				$data = array('group_id'=>$group_id);
				break;
			case 17:
				$isdriver = 1;
				$data = array('user_id'=>1,'user_name'=>'Meet Udeshi','fbid'=>'11323','group_id'=>$group_id);
				break;
			case 18:
				$isdriver = 1;
				$data = array('user_id'=>1,'user_name'=>'Meet Udeshi','fbid'=>'11323','group_id'=>$group_id);
				break;
		}
		if($check)
			return Error::make(0,100,$check);

		if($isdriver == 0)
			self::send_push(array($journey_id),intval(Input::get('msgcode')),$data);
		else
			self::driver_send_push(array($journey_id),intval(Input::get('msgcode')),$data);
	}
	public function add_event($journey_id,$msgcode,$data,$message)
	{
		$journey = Journey::where('journey_id','=',$journey_id)->first();
		$group_id = $journey->group_id;
		$event = new PendingEvent;
		//$driver->group_id = Input::get('group_id');
		$event->journey_id=$journey_id;
		$event->group_id=$group_id;
		$event->message_code = $msgcode;
		$event->message = $message;
		$event->data = json_encode($data);
		try {
			$event->save();
			return $event->id;
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function add_driver_event($driver_id,$msgcode,$data,$message)
	{
		$event = new DriverEvent;
		//$driver->group_id = Input::get('group_id');
		$event->driver_id=$driver_id;
		$event->message_code = $msgcode;
		$event->message = $message;
		$event->data = json_encode($data);
		try {
			$event->save();
			return $event->id;
		} catch (Exception $e) {
			return Error::make(101,101,$e->getMessage());
		}
	}
	public function driver_send_push($driver_ids,$msgcode,$data)
	{
		$message_data = array(
								16=>"Alloted to a new group!",
								17=>"Person left the group!",
								18=>"New user has joined!",
				);
		$message = $message_data[$msgcode];
		$data['time']=time();
		$data['driver_id']=0;
		foreach ($driver_ids as $driver_id1) {
				$data['driver_id']=$driver_id1;
				$driver = Driver::where('driver_id' , '=',$driver_id1)->first();
				$event_id = self::add_driver_event($driver_id1,$msgcode,$data,$message);
				$data['event_id']=$event_id;
				$uMsg = array();
				$uMsg['type'] = $msgcode;
				$uMsg['data'] = $data;
				$uMsg['message'] = $message;
				try {
				//Notifying all existing users about new guy
				$collection = PushNotification::app('Pickup')
	            	->to($driver->registration_id)
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
				$event_id = self::add_event($journey_id1,$msgcode,$data,$message);
				$data['event_id']=$event_id;
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
