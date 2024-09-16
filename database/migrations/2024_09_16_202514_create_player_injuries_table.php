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
            $table->integer('player_id')->unsigned();
            $table->integer('injury_id')->unsigned();
            $table->date('injury_start_date');
            $table->date('injury_end_date');

            $table->foreign('player_id')->references('id')->on('transfers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_injuries');
    }
};
