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
            $table->integer('salary')->unsigned()->default(0);
            $table->integer('appearance')->unsigned()->default(0);
            $table->integer('assist')->unsigned()->default(0);
            $table->integer('goal')->unsigned()->default(0);
            $table->integer('league')->unsigned()->default(0);
            $table->integer('pc_promotion_salary_raise')->unsigned()->default(0);
            $table->integer('pc_demotion_salary_cut')->unsigned()->default(0);
            $table->integer('cup')->unsigned()->default(0);
            $table->integer('el')->unsigned()->default(0);
            $table->integer('cl')->unsigned()->default(0);
            $table->integer('agent_fee')->unsigned()->default(0);

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
