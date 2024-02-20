<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ledger', function (Blueprint $table) {
            $table->id();
            $table->integer('transaction');
            $table->integer('receiving_account');
            $table->integer('sending_account');
            $table->dateTime('transaction_date');
            $table->double('amount');
            $table->integer('currency')->default(1);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ledger');
    }
};
