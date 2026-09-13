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
        Schema::create('stadium_commercial_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('stadium_id');
            $table->unsignedInteger('base_product_id');
            $table->string('category', 32);
            $table->unsignedInteger('base_price');
            $table->decimal('price_change_coef', 8, 4)->default(1);
            $table->boolean('is_available')->default(true);

            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->foreign('stadium_id')->references('id')->on('stadiums')->cascadeOnDelete();
            $table->foreign('base_product_id')->references('id')->on('base_commercial_products')->restrictOnDelete();
            $table->unique(['instance_id', 'stadium_id', 'base_product_id'], 'scp_instance_stadium_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stadium_commercial_products');
    }
};
