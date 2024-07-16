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
        Schema::create('transfer_financial_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transfer_id')->unsigned();
            $table->integer('transfer_contract_offer_id')->unsigned();
            $table->integer('amount')->unsigned();
            $table->integer('installments')->unsigned();
            $table->integer('fee')->unsigned();
            $table->integer('agent_fee')->unsigned();

            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
            $table->foreign('transfer_contract_offer_id')->references('id')->on('transfer_contract_offers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_financial_details');
    }
};
