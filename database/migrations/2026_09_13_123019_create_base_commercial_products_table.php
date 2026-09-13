<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('base_commercial_products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique();
            $table->string('category', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('base_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_commercial_products');
    }
};
