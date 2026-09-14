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
        Schema::table('stadium_commercial_venues', function (Blueprint $table) {
            $table->unsignedInteger('build_cost')->nullable()->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stadium_commercial_venues', function (Blueprint $table) {
            $table->dropColumn('build_cost');
        });
    }
};
