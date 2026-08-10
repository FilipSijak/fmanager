<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBaseClubsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('base_clubs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('country_code');
            $table->integer('city_id');
            $table->integer('stadium_id');
            $table->integer('rank');
            $table->integer('rank_academy');
            $table->integer('rank_training');
            $table->integer('financial_rank')->default(1);
            $table->bigInteger('starting_balance')->default(0);
            $table->bigInteger('allowed_debt')->default(0);
            $table->bigInteger('transfer_budget')->default(0);
            $table->bigInteger('salaries_yearly_budget')->default(0);
            $table->integer('competition_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('base_clubs');
    }
}
