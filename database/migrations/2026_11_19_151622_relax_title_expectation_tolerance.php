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
        DB::table('board_expectation_types')->where('rating', 100)->where('tolerance', 1)->update(['tolerance' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('board_expectation_types')->where('rating', 100)->where('tolerance', 2)->update(['tolerance' => 1]);
    }
};
