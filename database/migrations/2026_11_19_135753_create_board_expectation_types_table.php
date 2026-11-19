<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('board_expectation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('description');
            $table->unsignedTinyInteger('rating')->unique();
            $table->unsignedTinyInteger('tolerance');
        });

        DB::table('board_expectation_types')->insert([
            ['description' => 'Avoid relegation', 'rating' => 20, 'tolerance' => 2],
            ['description' => 'Finish in mid-table', 'rating' => 40, 'tolerance' => 3],
            ['description' => 'Finish in the top half', 'rating' => 60, 'tolerance' => 2],
            ['description' => 'Finish near the top', 'rating' => 80, 'tolerance' => 2],
            ['description' => 'Win the league', 'rating' => 100, 'tolerance' => 1],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_expectation_types');
    }
};
