<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTournamentKnockoutTable extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_knockout', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('instance_id');
            $table->unsignedInteger('competition_id');
            $table->unsignedInteger('season_id');
            $table->unsignedInteger('participant_count');
            $table->unsignedInteger('bracket_size');
            $table->string('status')->default('scheduled');
            $table->unsignedInteger('winner_club_id')->nullable();
            $table->timestamps();
            $table->unique(['instance_id', 'competition_id', 'season_id'], 'tk_edition_unique');
            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('season_id')->references('id')->on('seasons')->cascadeOnDelete();
            $table->foreign('winner_club_id')->references('id')->on('clubs')->nullOnDelete();
        });

        Schema::create('tournament_knockout_participants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tournament_knockout_id');
            $table->unsignedInteger('club_id');
            $table->unsignedInteger('seed')->nullable();
            $table->string('source')->nullable();
            $table->unsignedInteger('source_position')->nullable();
            $table->unique(['tournament_knockout_id', 'club_id'], 'tk_participant_unique');
            $table->foreign('tournament_knockout_id')->references('id')->on('tournament_knockout')->cascadeOnDelete();
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
        });

        Schema::create('tournament_knockout_rounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tournament_knockout_id');
            $table->unsignedInteger('round_number');
            $table->string('bracket_side');
            $table->string('name');
            $table->unsignedInteger('number_of_legs')->default(2);
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->unique(['tournament_knockout_id', 'round_number', 'bracket_side'], 'tk_round_unique');
            $table->foreign('tournament_knockout_id')->references('id')->on('tournament_knockout')->cascadeOnDelete();
        });

        Schema::create('tournament_knockout_ties', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('round_id');
            $table->unsignedInteger('position');
            $table->unsignedInteger('home_club_id')->nullable();
            $table->unsignedInteger('away_club_id')->nullable();
            $table->unsignedInteger('winner_club_id')->nullable();
            $table->unsignedBigInteger('next_tie_id')->nullable();
            $table->string('next_tie_slot')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->unique(['round_id', 'position'], 'tk_tie_position_unique');
            $table->foreign('round_id')->references('id')->on('tournament_knockout_rounds')->cascadeOnDelete();
            $table->foreign('home_club_id')->references('id')->on('clubs')->nullOnDelete();
            $table->foreign('away_club_id')->references('id')->on('clubs')->nullOnDelete();
            $table->foreign('winner_club_id')->references('id')->on('clubs')->nullOnDelete();
        });

        Schema::table('tournament_knockout_ties', function (Blueprint $table) {
            $table->foreign('next_tie_id')->references('id')->on('tournament_knockout_ties')->nullOnDelete();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('knockout_tie_id')->nullable()->after('competition_id');
            $table->unsignedInteger('leg_number')->nullable()->after('knockout_tie_id');
            $table->foreign('knockout_tie_id')->references('id')->on('tournament_knockout_ties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['knockout_tie_id']);
            $table->dropColumn(['knockout_tie_id', 'leg_number']);
        });
        Schema::dropIfExists('tournament_knockout_ties');
        Schema::dropIfExists('tournament_knockout_rounds');
        Schema::dropIfExists('tournament_knockout_participants');
        Schema::dropIfExists('tournament_knockout');
    }
}
