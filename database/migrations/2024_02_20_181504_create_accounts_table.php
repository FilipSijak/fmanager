<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('balance');
            $table->integer('future_balance');
            $table->integer('allowed_debt');
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounts');
    }
};
