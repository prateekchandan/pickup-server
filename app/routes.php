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
	Route::get('user/{user_id}', array('as' => 'user.add', 'uses' => 'UserController@get'));
	Route::post('add_journey', array('as' => 'journey.add', 'uses' => 'HomeController@journey_add'));
	Route::get('journey' , 'HomeController@MakeGroups');
	Route::get('journey/{id}' , 'HomeController@get_journey');
});

Route::get('verify/{code}',array('uses'=>'UserController@verify'));
Route::get('verify/',array('as'=>'verify','uses'=>'UserController@verify'));

Route::get('yo' , function(){
	echo "<h1>hello</h1>";
});
Route::get('mailtest',function()
{
	View::share('user' ,User::get()[0]);
	View::share('encryption','somecode');
	return View::make('emails.verify');
});
