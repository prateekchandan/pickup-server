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
		Schema::dropIfExists('pending');
		Schema::dropIfExists('users');
		Schema::create('users', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('email',50)->unique();
			$table->string('first_name', 200);
			$table->string('second_name', 200);
			$table->string('gender',10);
			$table->string('fbid',50);
			$table->string('device_id',100)->unique();
			$table->string('registration_id',1000)->nullable();
			$table->timestamps();
			$table->rememberToken();
		});

		Schema::create('pending', function(Blueprint $table)
		{
			$table->increments('journey_id');
			$table->float('start_lat');
			$table->float('start_long');
			$table->float('end_lat');
			$table->float('end_long');
			$table->integer('id')->unsigned();
			$table->foreign('id')->references('id')->on('users')->onDelete('cascade');
			$table->dateTime('journey_time');
			$table->integer('margin_before');
			$table->integer('margin_after');
			$table->integer('preference');
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
