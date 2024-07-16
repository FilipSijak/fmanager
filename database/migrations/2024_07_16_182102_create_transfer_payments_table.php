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
        Schema::create('transfer_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transfer_id')->unsigned();
            $table->integer('amount_left')->unsigned();
            $table->integer('installments_amount_left')->unsigned();
            $table->integer('installments_left')->unsigned();
            $table->timestamps();

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
        Schema::dropIfExists('transfer_payments');
    }
};
