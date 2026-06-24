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
            $table->integer('instance_id');
            $table->string('country_code');
            $table->integer('city_id')->unsigned()->nullable();
            $table->integer('stadium_id')->unsigned()->nullable();
            $table->integer('rank');
            $table->integer('rank_academy');
            $table->integer('rank_training');
            $table->integer('base_club_id')->unsigned()->nullable();
            $table->index(['instance_id', 'base_club_id']);

            $table->foreign('base_club_id')->references('id')->on('base_clubs')->onDelete('restrict');
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
