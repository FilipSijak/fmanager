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
        Schema::table('finance_entity_loans', function (Blueprint $table): void {
            $table->unique('stadium_stand_construction_id', 'finance_loans_stand_construction_unique');
            $table->unique('stadium_commercial_venue_id', 'finance_loans_commercial_venue_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_entity_loans', function (Blueprint $table): void {
            $table->dropUnique('finance_loans_stand_construction_unique');
            $table->dropUnique('finance_loans_commercial_venue_unique');
        });
    }
};
