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
        Schema::create('base_stadium_expansion_costs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stadium_type', 20)->unique();
            $table->unsignedInteger('cost_per_1000_seats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_stadium_expansion_costs');
    }
};
