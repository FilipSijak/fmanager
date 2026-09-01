<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->unsignedBigInteger('person_id');
            $table->string('position');
            $table->integer('potential');
            $table->integer('max_potential');

            foreach (array_merge(
                self::TECHNICAL_FIELDS,
                self::MENTAL_FIELDS,
                self::PHYSICAL_FIELDS
            ) as $field) {
                $table->integer($field)->default(50);
            }

            $table->integer('condition')->default(100);
            $table->integer('morale')->default(100);
            $table->timestamps();

            $table->unique('player_id');
            $table->index('instance_id');
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players_progress');
    }
};
