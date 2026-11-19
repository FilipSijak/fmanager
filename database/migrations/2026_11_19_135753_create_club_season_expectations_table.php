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
        Schema::create('club_season_expectations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('season_id');
            $table->foreign('season_id')->references('id')->on('seasons')->cascadeOnDelete();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->unsignedInteger('competition_id')->nullable();
            $table->foreign('competition_id')->references('id')->on('competitions')->nullOnDelete();
            $table->foreignId('board_expectation_type_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('tolerance');
            $table->unsignedTinyInteger('expectation_score');
            $table->unsignedSmallInteger('target_position')->nullable();
            $table->unsignedSmallInteger('league_size')->nullable();
            $table->bigInteger('budget');
            $table->bigInteger('transfer_budget');
            $table->decimal('team_quality', 5, 2);
            $table->unsignedTinyInteger('club_rank');
            $table->date('assigned_on');
            $table->unique(['instance_id', 'season_id', 'club_id'], 'club_season_expectations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_season_expectations');
    }
};
