<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('board_expectation_types')->where('rating', 40)->where('tolerance', 3)->update(['tolerance' => 4]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('board_expectation_types')->where('rating', 40)->where('tolerance', 4)->update(['tolerance' => 3]);
    }
};
