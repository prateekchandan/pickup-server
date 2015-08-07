<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddingDatabase extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::dropIfExists('ratings');
		Schema::dropIfExists('drivers');
		Schema::dropIfExists('groups');
		Schema::dropIfExists('created_journeys');
		Schema::dropIfExists('pending');
		Schema::dropIfExists('users');
		
		Schema::create('users', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('email',50)->unique();
			$table->string('first_name', 200);
			$table->string('age',20);
			$table->string('phone',20);
			$table->string('company',200);
			$table->string('company_email',200)->nullable();
			$table->string('second_name', 200);
			$table->string('gender',10);
			$table->string('fbid',50);
			$table->string('device_id',100)->unique();
			$table->string('registration_id',1000)->nullable();
			$table->string('mac_addr',200)->nullable();
			$table->string('current_pos',200)->default("19.1336,72.9154");
			$table->string('home_location',200)->default("none");
			$table->string('office_location',200)->default("none");
			$table->string('home_text',200)->default("none");
			$table->string('office_text',200)->default("none");
			$table->longtext('home_to_office');
			$table->longtext('office_to_home');
			$table->double('path_distance')->default(0);
			$table->double('path_time')->default(0);
			$table->time('leaving_home')->default("09:00:00");
			$table->time('leaving_office')->default("17:00:00");
			$table->double('total_distance_travelled')->default(0);
			$table->timestamps();
			$table->rememberToken();
		});

		Schema::create('pending', function(Blueprint $table)
		{
			$table->increments('journey_id');
			$table->integer('group_id')->nullable();
			$table->double('start_lat');
			$table->double('start_long');
			$table->double('end_lat');
			$table->double('end_long');
			$table->integer('id')->unsigned();
			$table->foreign('id')->references('id')->on('users')->onDelete('cascade');
			$table->dateTime('journey_time');
			$table->integer('margin_before');
			$table->integer('margin_after');
			$table->integer('preference');
			$table->string('start_text',300);
			$table->string('end_text',300);
			$table->double('distance');
			$table->double('time');
			$table->longtext('path');
			$table->longtext('path2')->nullable();
			$table->longtext('path3')->nullable();
			$table->double('distance_travelled')->default(0);
			$table->longtext('best_match')->nullable();
			$table->timestamps();
		});

		Schema::create('groups', function(Blueprint $table)
		{
			$table->increments('group_id');
			$table->longtext('journey_ids');
			$table->longtext('people_on_ride');
			$table->longtext('path_waypoints');
			$table->longtext('path')->nullable();
			$table->longtext('event_status')->nullable();
			$table->integer('driver_id')->nullable();
			$table->dateTime('journey_time');
			$table->dateTime('start_time');
			//$table->longtext('accept_third');
			$table->timestamps();
		});
/*
		Schema::create('chat',function (Blueprint $table)
		{
			$table->increments('message_id');
			$table->integer('group_id');
			$table->integer('user_id');
			$table->longtext('chat_massage');
			$table->foreign('group_id')->references('group_id')->on('groups')->onDelete('cascade');
			$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
		});*/
		
		Schema::create('created_journeys', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('u1')->unsigned()->nullable();
			$table->foreign('u1')->references('id')->on('users')->onDelete('cascade');
			$table->integer('u2')->unsigned()->nullable();
			$table->foreign('u2')->references('id')->on('users')->onDelete('cascade');
			$table->integer('u3')->unsigned()->nullable();
			$table->foreign('u3')->references('id')->on('users')->onDelete('cascade');
			$table->integer('j1')->unsigned()->nullable();
			$table->foreign('j1')->references('journey_id')->on('pending')->onDelete('cascade');
			$table->integer('j2')->unsigned()->nullable();
			$table->foreign('j2')->references('journey_id')->on('pending')->onDelete('cascade');
			$table->integer('j3')->unsigned()->nullable();
			$table->foreign('j3')->references('journey_id')->on('pending')->onDelete('cascade');
			$table->text("event_status");
			$table->double('u1_distance');
			$table->double('u1_time');
			$table->double('u2_distance');
			$table->double('u2_time');
			$table->double('u3_distance');
			$table->double('u3_time');
			$table->longtext('path');
			$table->timestamps();
		});
	
	Schema::create('drivers', function(Blueprint $table)
		{
			$table->increments('driver_id');
			$table->string('driver_name', 200);
			$table->string('username',200);
			$table->string('password',200);
			$table->string('driver_address',500)->nullable();
			$table->string('license_details',200)->nullable();
			$table->longtext('images');
			$table->string('current_pos',200)->default("19.1336,72.9154");
			$table->string('phone',200);
			//$table->string('profile_picture',500)->nullable();
			$table->string('car_model',200)->nullable();
			$table->string('car_number',200)->nullable();
			$table->string('registration_id',1000)->nullable();
			$table->string('driver_status',200)->default("vacant");
			$table->integer('group_id')->unsigned()->nullable();
			$table->foreign('group_id')->references('group_id')->on('groups')->onDelete('cascade');
			$table->timestamps();
			$table->rememberToken();
		});

		Schema::create('ratings', function(Blueprint $table)
		{
			$table->increments('group_id');
			$table->longtext('journey_ids');
			$table->longtext('people_on_ride');
			$table->longtext('path_waypoints');
			$table->longtext('path')->nullable();
			$table->longtext('event_status')->nullable();
			$table->integer('driver_id')->nullable();
			$table->dateTime('journey_time');
			$table->dateTime('start_time');
			//$table->longtext('accept_third');
			$table->timestamps();
		});
}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		//
	}

}
