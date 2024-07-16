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
        Schema::create('transfer_types', function (Blueprint $table) {
            $table->id();
            $table->string('FREE_TRANSFER', 30);
            $table->string('LOAN_TRANSFER', 30);
            $table->string('PERMANENT_TRANSFER', 30);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_types');
    }
};
