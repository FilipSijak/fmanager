<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_tier_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('upper_base_competition_id');
            $table->unsignedBigInteger('lower_base_competition_id');
            $table->unsignedInteger('automatic_movement_places')->default(0);
            $table->unsignedInteger('promotion_playoff_places')->default(0);
            $table->unsignedInteger('relegation_playoff_places')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['upper_base_competition_id', 'lower_base_competition_id'], 'league_tier_pair_unique');
            $table->foreign('upper_base_competition_id', 'ltr_upper_fk')->references('id')->on('base_competitions')->cascadeOnDelete();
            $table->foreign('lower_base_competition_id', 'ltr_lower_fk')->references('id')->on('base_competitions')->cascadeOnDelete();
        });

        Schema::create('competition_qualification_rules', function (Blueprint $table): void {
            $table->id();
            // Source is where qualification is earned; target is the competition the club qualifies for.
            $table->unsignedBigInteger('source_base_competition_id')
                ->comment('Competition where the club earns qualification');
            $table->unsignedBigInteger('target_base_competition_id')
                ->comment('Competition the club qualifies to enter');
            $table->string('selector_type');
            $table->unsignedInteger('position_from')->nullable();
            $table->unsignedInteger('position_to')->nullable();
            $table->string('entry_stage')->default('group_stage');
            $table->string('duplicate_policy')->default('next_league_position');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['source_base_competition_id', 'active'], 'qualification_source_idx');
            $table->foreign('source_base_competition_id', 'cqr_source_fk')->references('id')->on('base_competitions')->cascadeOnDelete();
            $table->foreign('target_base_competition_id', 'cqr_target_fk')->references('id')->on('base_competitions')->cascadeOnDelete();
        });

        Schema::create('club_competition_movements', function (Blueprint $table): void {
            $table->id();
            $table->integer('instance_id');
            $table->unsignedInteger('source_season_id');
            $table->unsignedInteger('club_id');
            $table->unsignedInteger('from_competition_id');
            $table->unsignedInteger('to_competition_id');
            $table->string('type');
            $table->unsignedInteger('source_position');
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['source_season_id', 'club_id', 'type'], 'club_movement_unique');
            $table->foreign('source_season_id', 'ccm_season_fk')->references('id')->on('seasons')->cascadeOnDelete();
            $table->foreign('club_id', 'ccm_club_fk')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreign('from_competition_id', 'ccm_from_fk')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('to_competition_id', 'ccm_to_fk')->references('id')->on('competitions')->cascadeOnDelete();
        });

        Schema::create('club_competition_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->integer('instance_id');
            $table->unsignedInteger('source_season_id');
            $table->unsignedInteger('club_id');
            $table->unsignedInteger('source_competition_id');
            $table->unsignedInteger('target_competition_id');
            $table->unsignedBigInteger('target_base_competition_id');
            $table->string('qualification_type');
            $table->unsignedInteger('source_position')->nullable();
            $table->string('entry_stage');
            $table->unsignedInteger('priority');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['source_season_id', 'club_id'], 'club_season_qualification_unique');
            $table->foreign('source_season_id', 'ccq_season_fk')->references('id')->on('seasons')->cascadeOnDelete();
            $table->foreign('club_id', 'ccq_club_fk')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreign('source_competition_id', 'ccq_source_fk')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('target_competition_id', 'ccq_target_fk')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('target_base_competition_id', 'ccq_target_base_fk')->references('id')->on('base_competitions')->cascadeOnDelete();
        });

        if (Schema::hasTable('competition_hierarchy')) {
            $pairs = DB::table('competition_hierarchy')
                ->whereNotNull('child_competition_id')
                ->select('competition_id', 'child_competition_id')
                ->distinct()->get();
            foreach ($pairs as $pair) {
                DB::table('league_tier_rules')->insertOrIgnore([
                    'upper_base_competition_id' => $pair->competition_id,
                    'lower_base_competition_id' => $pair->child_competition_id,
                    'automatic_movement_places' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_competition_qualifications');
        Schema::dropIfExists('club_competition_movements');
        Schema::dropIfExists('competition_qualification_rules');
        Schema::dropIfExists('league_tier_rules');
    }
};
