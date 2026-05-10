<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('player_injuries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('season_id');
            $table->integer('player_id')->unsigned();
            $table->unsignedBigInteger('injury_id');
            $table->date('injury_start_date');
            $table->date('injury_end_date');

            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            $table->foreign('injury_id')->references('id')->on('injuries')->onDelete('cascade');
            $table->foreign('instance_id')->references('id')->on('instances')->onDelete('cascade');
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_injuries');
    }
};
