<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatedJourney extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
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
			$table->double('u1_distance');
			$table->double('u1_time');
			$table->double('u2_distance');
			$table->double('u2_time');
			$table->double('u3_distance');
			$table->double('u3_time');
			$table->longtext('path');
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
