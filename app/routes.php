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
Route::post('add_user', array('as' => 'user.add', 'uses' => 'UserController@add'));
Route::post('register_gcm', array('as' => 'user.gcm', 'uses' => 'UserController@gcm_add'));

Route::get('user/{user_id}', array('as' => 'user.add', 'uses' => 'UserController@get'));

Route::post('add_journey', array('as' => 'journey.add', 'uses' => 'HomeController@journey_add'));

Route::get('journey' , 'HomeController@MakeGroups');

Route::get('mailtest',function()
{
	Mail::send('emails.test', array('firstname'=>'Prateek Chandan'), function($message){
        $message->to("mittal.shivam5@gmail.com", 'Prateek Chandan')->subject('Welcome to Pickup Mail test!');
    });
});
