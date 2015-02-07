<?php


class User extends Eloquent{

	protected $table = 'users';
	protected $hidden = array('registration_id','created_at','updated_at','remember_token');

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */



}
