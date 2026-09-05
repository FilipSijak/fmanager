<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->integer('instance_id')->unsigned()->index();
            $table->integer('season_id')->unsigned()->index();
            $table->integer('competition_id');
            $table->integer('hometeam_id')->unsigned();
            $table->integer('awayteam_id')->unsigned();
            $table->integer('stadium_id')->unsigned();
            $table->integer('attendance')->nullable();
            $table->dateTime('match_start')->nullable();
            $table->integer('winner')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->timestamp('processed_at')->nullable();
            $table->integer('home_team_goals')->nullable();
            $table->integer('away_team_goals')->nullable();
            $table->json('match_summary')->nullable();

            $table->index(
                ['instance_id', 'match_start', 'status'],
                'games_instance_match_start_status_idx'
            );
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
