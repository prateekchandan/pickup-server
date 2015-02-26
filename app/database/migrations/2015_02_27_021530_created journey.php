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
			$table->integer('u1')->unsigned();
			$table->foreign('u1')->references('id')->on('users')->onDelete('cascade');
			$table->integer('u2')->unsigned();
			$table->foreign('u2')->references('id')->on('users')->onDelete('cascade');
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
