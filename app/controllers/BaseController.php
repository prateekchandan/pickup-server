<?php
/**
 * BaseController
 *
 * Contains all basic functionality used by all Controllers.
 * Must be inherited by each and every Controller created.
 *
 * @author Kalpesh Krishna <kalpeshk2011@gmail.com>
 * @copyright 2015 Pickup 
*/
class BaseController extends Controller {

	private $privatekey = "PickupMailCheckingYo!";

	/**
	 * Used to check validity of HTTP request input.
	 *
	 * All routes created must validate input using this function.
	 * This function will throw the approprate error.
	 *
	 * @param $requirements
	 *		  (string[]) - An array containing strings of all required parameters
	 * @return (string) name of missing parameter, (boolean) false if none.
	*/
	public function check_requirements($requirements) {

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

	/**
	 * Helper function to log data into match_logs.txt if a match is found.
	 *
	 * Should be used in the HomeController::get_best_match() function or an
	 * equivalent function only. Function adds a timestamp alongwith data.
	 *
	 * @param $data
	 *	  	  (string) - JSON encoded data to be written to log file.
	 * @return void
	*/
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

	/**
	 * Helper function to log data into cronlog.txt with push notification output
	 * or with outputs of any route executed via cron.
	 *
	 * Should be used if we wish to log data found in functions within controllers.
	 * Function adds a timestamp alongwith data.
	 *
	 * @param $data
	 *	  	  (string) - JSON encoded data to be written to log file.
	 * @return void
	*/
	public function log_data($data)
	{
	$file = fopen(storage_path()."/logs/cronlog.txt",'a');
	fwrite($file,$data);
	if($data != ""){
        $t = date("Y-m-d G:i:s",time());
        $data ="\nPinged the server now at ".$t."\n----------------------------------\n\n";
	}
	fwrite($file,$data);
	}

	/**
	 * Helper function used to get all the events which have not been
	 * received by the user.
	 *
	 * Used by UserController::periodic_route() to implement push notification
	 * backup mechanism. The function receives all event IDs which have been
	 * received by the user and deletes these IDs from the storage table.
	 * It returns the residue event notifications (which weren't received by the
	 * user so far) and deletes the same from the events table.
	 *
	 * @param $journey_id
	 *	  	  (integer) - Journey ID of person for whom periodic_route() is executed.
	 * @param $event_ids
	 *		  (integer[]) - Array with all event IDs received by user so far.
	 * @return (Object[]) - Array of all pending events.
	*/
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

	/**
	 * A function used to send push notifications to the user of a journey_id.
	 *
	 * This function has been created to ease the debugging procedure.
	 * It will send a push notification to a user using his journey_id.
	 * Choice of message solely decided by message code sent via HTTP request.
	 * 
	 * Used by route :-
	 * Route::get('push_test/{id}','BaseController@push_test')
	 *
	 * @param $journey_id
	 *	  	  (integer) - Journey ID of user to whom the push notification must be sent.
	 * @return void
	*/
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
