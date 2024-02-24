<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts_debt_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('account_id');
            $table->integer('receiving_account_id');
            $table->integer('installment');
            $table->date('expiration');
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounts_debt_lines');
    }
};
