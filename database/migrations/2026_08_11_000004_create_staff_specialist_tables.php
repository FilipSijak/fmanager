<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('staff_physio', function (Blueprint $table): void {
            $table->id();
            $table->integer('instance_id')->index();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedInteger('club_id')->nullable();
            $table->foreignId('contract_id')->nullable()->constrained('staff_contracts')->nullOnDelete();
            $table->foreign('club_id')->references('id')->on('clubs')->nullOnDelete();
            $table->string('team_type');
            $table->boolean('is_retired')->default(false);
            $table->unsignedTinyInteger('physiotherapy');
            $table->unsignedTinyInteger('injury_prevention');
            $table->unsignedTinyInteger('rehabilitation');
            $table->unsignedTinyInteger('sports_science');
            $table->unsignedTinyInteger('fitness_assessment');

            $table->index(['instance_id', 'is_retired']);
        });

        Schema::create('staff_scouts', function (Blueprint $table): void {
            $table->id();
            $table->integer('instance_id')->index();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedInteger('club_id')->nullable();
            $table->foreignId('contract_id')->nullable()->constrained('staff_contracts')->nullOnDelete();
            $table->foreign('club_id')->references('id')->on('clubs')->nullOnDelete();
            $table->string('region')->nullable();
            $table->boolean('is_retired')->default(false);
            $table->unsignedTinyInteger('judging_player_ability');
            $table->unsignedTinyInteger('judging_player_potential');
            $table->unsignedTinyInteger('tactical_knowledge');
            $table->unsignedTinyInteger('data_analysis');
            $table->unsignedTinyInteger('market_knowledge');

            $table->index(['instance_id', 'is_retired']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_scouts');
        Schema::dropIfExists('staff_physio');
    }
};
