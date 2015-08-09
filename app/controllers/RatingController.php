<?php

class RatingController extends BaseController {

	/*
	|--------------------------------------------------------------------------
	| Default Home Controller
	|--------------------------------------------------------------------------
	|
	| You may wish to use controllers instead of, or in addition to, Closure
	| based routes. That's great! Here is an example controller method to
	| get you started. To route to this controller, just add the route:
	|
	|	Route::get('/', 'HomeController@showWelcome');
	|
	*/
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
}
