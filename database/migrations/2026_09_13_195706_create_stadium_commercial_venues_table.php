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
        Schema::create('stadium_commercial_venues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('stadium_id');
            $table->unsignedInteger('category_id');
            $table->unsignedTinyInteger('size');
            $table->unsignedInteger('build_cost')->nullable();

            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->foreign('stadium_id')->references('id')->on('stadiums')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('base_commercial_categories')->restrictOnDelete();
            $table->foreign(['instance_id', 'stadium_id'], 'scv_instance_stadium_foreign')->references(['instance_id', 'id'])->on('stadiums')->cascadeOnDelete();
            $table->unique(['instance_id', 'stadium_id', 'category_id'], 'scv_instance_stadium_category_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stadium_commercial_venues');
    }
};
