<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TECHNICAL_FIELDS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick', 'heading',
        'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking', 'tackling', 'technique',
    ];

    private const MENTAL_FIELDS = [
        'aggression', 'anticipation', 'bravery', 'composure', 'concentration', 'creativity',
        'decisions', 'determination', 'flair', 'leadership', 'of_the_ball', 'positioning',
        'teamwork', 'workrate',
    ];

    private const PHYSICAL_FIELDS = [
        'acceleration', 'agility', 'balance', 'jumping', 'natural_fitness', 'pace', 'stamina',
        'strength',
    ];

    public function up(): void
    {
        Schema::create('players_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('player_id');
            $table->integer('instance_id');
            foreach (array_merge(
                self::TECHNICAL_FIELDS,
                self::MENTAL_FIELDS,
                self::PHYSICAL_FIELDS
            ) as $field) {
                $table->unsignedSmallInteger($field)->default(50);
            }

            $table->unsignedTinyInteger('condition')->default(100);
            $table->unsignedTinyInteger('morale')->default(100);
            $table->timestamp('last_progressed_at')->nullable();
            $table->timestamps();

            $table->unique('player_id');
            $table->index('instance_id');
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE players_progress ADD CONSTRAINT players_progress_condition_check CHECK (`condition` BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE players_progress ADD CONSTRAINT players_progress_morale_check CHECK (`morale` BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('players_progress');
    }
};
