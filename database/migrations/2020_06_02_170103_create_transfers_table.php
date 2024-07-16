<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
         * transfer types = 1 -> free transfer, 2 -> loan transfer, 3 -> permanent transfer
         *
         * transfer status = 1 -> pending, 2 -> rejected, 3 -> accepted
         */

        Schema::create('transfers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('season_id');
            $table->integer('source_club_id');
            $table->integer('target_club_id')->nullable();
            $table->integer('player_id');
            $table->date('offer_date')->nullable();
            $table->date('transfer_date')->nullable();
            $table->tinyInteger('player_status');
            $table->tinyInteger('source_club_status');
            $table->tinyInteger('target_club_status');
            $table->integer('transfer_type');
            $table->date('loan_start')->nullable();
            $table->date('loan_end')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfers');
    }
}
