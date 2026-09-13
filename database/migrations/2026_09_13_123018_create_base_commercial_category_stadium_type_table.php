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
        Schema::create('base_commercial_category_stadium_type', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->string('stadium_type', 20);
            $table->foreign('category_id')->references('id')->on('base_commercial_categories')->cascadeOnDelete();
            $table->unique(['category_id', 'stadium_type'], 'bccst_category_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_commercial_category_stadium_type');
    }
};
