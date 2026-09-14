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
        Schema::create('base_stadium_stand_capacity_limits', function (Blueprint $table) {
            $table->id();
            $table->string('stadium_type', 20);
            $table->string('position', 20);
            $table->unsignedInteger('maximum_capacity');
            $table->unique(['stadium_type', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_stadium_stand_capacity_limits');
    }
};
