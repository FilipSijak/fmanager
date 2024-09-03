<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_contract_offers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transfer_id')->unsigned();
            $table->integer('salary')->unsigned();
            $table->integer('appearance')->unsigned();
            $table->integer('assist')->unsigned();
            $table->integer('goal')->unsigned();
            $table->integer('league')->unsigned();
            $table->integer('pc_promotion_salary_raise')->unsigned();
            $table->integer('pc_demotion_salary_cut')->unsigned();
            $table->integer('cup')->unsigned();
            $table->integer('el')->unsigned();
            $table->integer('cl')->unsigned();
            $table->integer('agent_fee')->unsigned();

            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_contract_offer');
    }
};
