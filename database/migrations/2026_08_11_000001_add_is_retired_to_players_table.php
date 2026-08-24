<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->boolean('is_retired')->default(false)->after('contract_id');
            $table->index(
                ['instance_id', 'is_retired', 'position', 'potential'],
                'players_active_position_potential_idx'
            );
            $table->index(
                ['instance_id', 'is_retired', 'club_id', 'position', 'potential'],
                'players_active_club_position_potential_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropIndex('players_active_position_potential_idx');
            $table->dropIndex('players_active_club_position_potential_idx');
            $table->dropColumn('is_retired');
        });
    }
};
