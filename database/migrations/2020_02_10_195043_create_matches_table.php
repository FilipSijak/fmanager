<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('competition_id');
            $table->integer('hometeam_id')->unsigned();
            $table->integer('awayteam_id')->unsigned();
            $table->integer('stadium_id')->unsigned();
            $table->integer('attendance')->nullable();
            $table->dateTime('match_start')->nullable();
            $table->integer('winner')->nullable();
            $table->integer('home_team_goals')->nullable();
            $table->integer('away_team_goals')->nullable();
            $table->json('match_summary')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('matches');
    }
}
