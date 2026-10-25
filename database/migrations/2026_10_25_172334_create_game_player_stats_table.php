<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COUNTERS = [
        'goals', 'assists', 'shots', 'shots_on_target', 'key_passes',
        'passes_attempted', 'passes_completed', 'tackles_attempted', 'tackles_won', 'interceptions',
        'fouls_committed', 'fouls_won', 'yellow_cards', 'red_cards', 'offsides',
        'saves', 'goals_conceded',
    ];

    public function up(): void
    {
        Schema::create('game_player_stats', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('game_id');
            $table->unsignedInteger('club_id');
            $table->unsignedInteger('player_id');
            $table->string('position', 10);
            $table->boolean('started');
            $table->unsignedTinyInteger('minutes_played');

            foreach (self::COUNTERS as $counter) {
                $table->unsignedSmallInteger($counter)->default(0);
            }

            $table->boolean('clean_sheet')->default(false);
            $table->decimal('rating', 3, 1);

            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->unique(['game_id', 'player_id']);
            $table->index(['player_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_stats');
    }
};
