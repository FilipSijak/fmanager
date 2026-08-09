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
            $table->integer('group_id')->nullable();
            $table->integer('points')->default(0);
            $table->integer('goals_for')->default(0);
            $table->integer('goals_against')->default(0);
            $table->integer('played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('losses')->default(0);
            $table->index(['instance_id', 'season_id', 'competition_id']);
            $table->index(['instance_id', 'season_id', 'competition_id', 'group_id'], 'cs_group_lookup_idx');
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
