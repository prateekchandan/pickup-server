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


	

}
