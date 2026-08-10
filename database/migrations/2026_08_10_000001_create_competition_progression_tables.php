<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_progression_rules', function (Blueprint $table): void {
            $table->id();
            // Source is where the place is earned; target is the competition the club enters.
            $table->unsignedBigInteger('source_base_competition_id')
                ->comment('Competition where the club earns progression');
            $table->unsignedBigInteger('target_base_competition_id')
                ->comment('Competition the club progresses into');
            $table->string('progression_type')->comment('promotion, relegation or continental');
            $table->string('selector_type')->comment('position_range, bottom_positions or competition_winner');
            $table->unsignedInteger('position_from')->nullable();
            $table->unsignedInteger('position_to')->nullable();
            $table->unsignedInteger('places')->nullable();
            $table->string('entry_stage')->nullable();
            $table->string('duplicate_policy')->default('next_league_position');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['source_base_competition_id', 'active'], 'progression_rule_source_idx');
            $table->foreign('source_base_competition_id', 'cpr_source_fk')
                ->references('id')->on('base_competitions')->cascadeOnDelete();
            $table->foreign('target_base_competition_id', 'cpr_target_fk')
                ->references('id')->on('base_competitions')->cascadeOnDelete();
        });

        Schema::create('club_competition_progressions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->integer('instance_id');
            $table->unsignedInteger('source_season_id');
            $table->unsignedInteger('club_id');
            $table->unsignedInteger('source_competition_id');
            $table->unsignedInteger('target_competition_id');
            $table->string('progression_type');
            $table->unsignedInteger('source_position')->nullable();
            $table->string('entry_stage')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['source_season_id', 'club_id', 'target_competition_id'], 'club_progression_unique');
            $table->foreign('rule_id', 'ccp_rule_fk')->references('id')->on('competition_progression_rules')->cascadeOnDelete();
            $table->foreign('source_season_id', 'ccp_season_fk')->references('id')->on('seasons')->cascadeOnDelete();
            $table->foreign('club_id', 'ccp_club_fk')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreign('source_competition_id', 'ccp_source_fk')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('target_competition_id', 'ccp_target_fk')->references('id')->on('competitions')->cascadeOnDelete();
        });

        if (Schema::hasTable('competition_hierarchy')) {
            $pairs = DB::table('competition_hierarchy')
                ->whereNotNull('child_competition_id')
                ->select('competition_id', 'child_competition_id')->distinct()->get();
            foreach ($pairs as $pair) {
                DB::table('competition_progression_rules')->insert([
                    [
                        'source_base_competition_id' => $pair->competition_id,
                        'target_base_competition_id' => $pair->child_competition_id,
                        'progression_type' => 'relegation',
                        'selector_type' => 'bottom_positions',
                        'places' => 3,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'source_base_competition_id' => $pair->child_competition_id,
                        'target_base_competition_id' => $pair->competition_id,
                        'progression_type' => 'promotion',
                        'selector_type' => 'position_range',
                        'position_from' => 1,
                        'position_to' => 3,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_competition_progressions');
        Schema::dropIfExists('competition_progression_rules');
    }
};
