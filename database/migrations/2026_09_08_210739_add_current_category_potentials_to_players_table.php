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
        Schema::table('players', function (Blueprint $table): void {
            $table->integer('current_technical_potential')->default(0)->after('technical');
            $table->integer('current_mental_potential')->default(0)->after('mental');
            $table->integer('current_physical_potential')->default(0)->after('physical');
        });

        DB::table('players')->update([
            'current_technical_potential' => DB::raw('technical'),
            'current_mental_potential' => DB::raw('mental'),
            'current_physical_potential' => DB::raw('physical'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn([
                'current_technical_potential',
                'current_mental_potential',
                'current_physical_potential',
            ]);
        });
    }
};
