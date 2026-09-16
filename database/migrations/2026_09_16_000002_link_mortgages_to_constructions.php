<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stadium_stand_constructions', function (Blueprint $table): void {
            $table->date('completed_at')->nullable()->after('completes_at');
        });

        Schema::table('finance_entity_loans', function (Blueprint $table): void {
            $table->foreignId('stadium_stand_construction_id')
                ->nullable()
                ->after('loan_type')
                ->constrained('stadium_stand_constructions')
                ->restrictOnDelete();
            $table->foreignId('stadium_commercial_venue_id')
                ->nullable()
                ->after('stadium_stand_construction_id')
                ->constrained('stadium_commercial_venues')
                ->restrictOnDelete();

            $table->index('stadium_stand_construction_id');
            $table->index('stadium_commercial_venue_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_entity_loans', function (Blueprint $table): void {
            $table->dropForeign(['stadium_stand_construction_id']);
            $table->dropForeign(['stadium_commercial_venue_id']);
            $table->dropIndex(['stadium_stand_construction_id']);
            $table->dropIndex(['stadium_commercial_venue_id']);
            $table->dropColumn(['stadium_stand_construction_id', 'stadium_commercial_venue_id']);
        });

        Schema::table('stadium_stand_constructions', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
