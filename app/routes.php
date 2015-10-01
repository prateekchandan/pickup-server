<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

// Adding data

// PUBLIC Group
Route::group(array('before'=>'API' ,'after'=>'afterAPI') ,function (){
	Route::post('add_user',
		array('as' => 'user.add', 'uses' => 'UserController@add'));
	Route::get('user_exists','UserController@check_existence');
	Route::get('get_group/{id}',
		array('as' => 'group.get', 'uses' => 'HomeController@get_group'));
	Route::get('user/{user_id}',
		array('as' => 'user.add', 'uses' => 'UserController@get'));
	Route::get('get_pending/{id}/' , 'HomeController@get_pending');
	Route::post('add_driver',
		array('as' => 'driver.add', 'uses' => 'DriverController@add'));
	Route::get('allocate_driver','DriverController@allocate_driver');
	Route::get('get_driver/{id}','DriverController@get');
	Route::post('send_push/{id}','HomeController@send_push');
	Route::get('push_test/{id}','BaseController@push_test');
	Route::get('get_picture/{id}','DriverController@get_picture');
	Route::post('driver_login','DriverController@driver_login');
	Route::post('add_rating','RatingController@add_rating');
	Route::get('get_address/{lat}/{long}','HomeController@get_address');
	
});

// USER GROUP
Route::group(array('before'=>'API' ,'after'=>'afterAPI') ,function (){
	Route::post('register_gcm', 
		array('as' => 'user.gcm','uses' => 'UserController@gcm_add'));
	Route::post('add_journey', 
		array('as' => 'journey.add','uses' => 'HomeController@journey_add'));
	Route::post('set_home','UserController@set_home');
	Route::post('set_office','UserController@set_office');
	Route::any('delete_journey/{id}', 
		array('as' => 'journey.delete', 'uses' => 'HomeController@journey_delete'));
	Route::get('get_best_match/{id}','HomeController@get_best_match');
	Route::get('confirm/{id}','HomeController@add_to_group');
	Route::post('journey_request','HomeController@journey_request');
	Route::get('user/{user_id}/all_journey', 
		array('as' => 'user.journey','uses' => 'UserController@all_journey'));
	Route::post('periodic_route/{id}','UserController@periodic_route');
	Route::get('get_history/{id}','UserController@get_history');
	Route::get('cancel_journey/{id}','HomeController@cancel_journey');
	
	
});

// DRIVER GROUP
Route::group(array('before'=>'API' ,'after'=>'afterAPI') ,function (){
	Route::post('driver_register_gcm', 'DriverController@driver_gcm_add');
	Route::post('driver_periodic_route/{id}','DriverController@driver_periodic_route');
	Route::post('upload_picture/{id}','DriverController@upload_picture');
	Route::get('get_detailed_group/{id}','DriverController@get_detailed_group');
	Route::get('group_enlist/{id}','DriverController@group_enlist');
	Route::post('end_journey/{id}','DriverController@end_journey');
	Route::post('picked_up_person/{id}','DriverController@picked_up_person');
	Route::post('add_group_to_driver/{id}',
		array('as' => 'driver.add_group', 'uses' => 'DriverController@give_driver_group'));
	
});

Route::get('verify/{code}',array('uses'=>'UserController@verify'));
Route::get('verify/',array('as'=>'verify','uses'=>'UserController@verify'));

Route::get('mailtest',function()
{
	View::share('user' ,User::get()[0]);
	View::share('encryption','somecode');
	return View::make('emails.verify');
});
