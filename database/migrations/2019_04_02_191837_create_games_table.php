<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('games', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('hometeam_id')->unsigned();
            $table->foreign('hometeam_id')->references('id')->on('clubs');
            $table->integer('awayteam_id')->unsigned();
            $table->foreign('awayteam_id')->references('id')->on('clubs');
            $table->integer('stadium_id')->unsigned();
            $table->foreign('stadium_id')->references('id')->on('stadiums');
            $table->integer('attendance');
            $table->dateTime('game_start');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('games');
    }
}
