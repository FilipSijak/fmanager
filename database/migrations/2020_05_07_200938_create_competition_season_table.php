<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompetitionSeasonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('competition_season', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('instance_id');
            $table->integer('competition_id');
            $table->integer('season_id');
            $table->integer('club_id');
            $table->integer('points')->nullable();
            $table->index(['instance_id', 'season_id', 'competition_id']);
            $table->index(['instance_id', 'season_id', 'club_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_season');
    }
}
