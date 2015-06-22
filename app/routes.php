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
Route::group(array('before'=>'API' ,'after'=>'afterAPI') ,function (){
	Route::post('add_user', array('as' => 'user.add', 'uses' => 'UserController@add'));
	Route::post('register_gcm', array('as' => 'user.gcm', 'uses' => 'UserController@gcm_add'));
	Route::get('get_group' , array('as' => 'group.get', 'uses' => 'HomeController@get_group'));
	//Route::get('find_mates/{id}',array('as' => 'journey.find_mates', 'uses' => 'HomeController@find_mates'));
	Route::get('user/{user_id}', array('as' => 'user.add', 'uses' => 'UserController@get'));
	Route::get('user/{user_id}/all_journey', array('as' => 'user.journey', 'uses' => 'UserController@all_journey'));
	Route::post('add_journey', array('as' => 'journey.add', 'uses' => 'HomeController@journey_add'));
	//Route::post('add_mates/{id}', array('as' => 'journey.add_mates', 'uses' => 'HomeController@add_mates'));
	//Route::post('edit_journey/{id}', array('as' => 'journey.edit', 'uses' => 'HomeController@journey_edit'));
	Route::any('delete_journey/{id}', array('as' => 'journey.delete', 'uses' => 'HomeController@journey_delete'));
	//Route::get('journey' , 'HomeController@MakeGroups');
	Route::get('get_pending/{id}/' , 'HomeController@get_pending');
	
	//Route::post('match_third/{id}/' , 'HomeController@match_third');
	Route::get('journey/{id}/{user?}' , 'HomeController@get_journey');
	Route::post('modify_location/{id}','HomeController@modify_location');
	Route::any('change_event/{id}' , 'HomeController@event_change');
	Route::post('add_driver', array('as' => 'driver.add', 'uses' => 'DriverController@add'));
	Route::post('add_group_to_driver/{id}', array('as' => 'driver.add_group', 'uses' => 'DriverController@give_driver_group'));
	Route::post('driver_modify_location/{id}','DriverController@modify_location');
	Route::get('group_enlist/{id}','DriverController@group_enlist');
	Route::post('end_journey_of_person/{id}','DriverController@end_journey');
	Route::get('allocate_driver','DriverController@allocate_driver');
	Route::get('get_driver/{id}','DriverController@get');
	Route::post('send_push/{id}','HomeController@send_push');
	Route::post('arbit_stuff','DriverController@arbit_stuff');
});

Route::get('verify/{code}',array('uses'=>'UserController@verify'));
Route::get('verify/',array('as'=>'verify','uses'=>'UserController@verify'));

Route::get('mailtest',function()
{
	View::share('user' ,User::get()[0]);
	View::share('encryption','somecode');
	return View::make('emails.verify');
});
