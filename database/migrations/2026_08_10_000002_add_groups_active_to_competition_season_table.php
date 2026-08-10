<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_season', function (Blueprint $table): void {
            $table->boolean('groups_active')->default(false)->after('group_id');
        });

        $groupCompetitionIds = DB::table('competitions')
            ->where('type', 'tournament')
            ->where('groups', 1)
            ->pluck('id');

        foreach ($groupCompetitionIds as $competitionId) {
            $knockoutSeasonIds = DB::table('tournament_knockout')
                ->where('competition_id', $competitionId)
                ->pluck('season_id');

            DB::table('competition_season')
                ->where('competition_id', $competitionId)
                ->when(
                    $knockoutSeasonIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('season_id', $knockoutSeasonIds)
                )
                ->update(['groups_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('competition_season', function (Blueprint $table): void {
            $table->dropColumn('groups_active');
        });
    }
};
