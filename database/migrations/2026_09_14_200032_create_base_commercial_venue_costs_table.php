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
        Schema::create('base_commercial_venue_costs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->unsignedTinyInteger('size');
            $table->unsignedInteger('base_cost');
            $table->foreign('category_id')->references('id')->on('base_commercial_categories')->cascadeOnDelete();
            $table->unique(['category_id', 'size'], 'bcc_cost_category_size_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_commercial_venue_costs');
    }
};
