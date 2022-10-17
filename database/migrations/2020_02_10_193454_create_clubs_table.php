<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClubsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('game_id');
            $table->string('country_code');
            $table->integer('city_id')->unsigned()->nullable();
            $table->integer('stadium_id')->unsigned()->nullable();
            $table->integer('rank');
            $table->integer('rank_academy');
            $table->integer('rank_training');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clubs');
    }
}
