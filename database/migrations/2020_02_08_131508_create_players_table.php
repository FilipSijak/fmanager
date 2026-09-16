<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayersTable extends Migration
{
    private const TECHNICAL_FIELDS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick', 'heading', 'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking', 'tackling', 'technique',
    ];

    private const MENTAL_FIELDS = [
        'aggression', 'anticipation', 'bravery', 'composure', 'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership', 'of_the_ball', 'positioning', 'teamwork', 'workrate',
    ];

    private const PHYSICAL_FIELDS = [
        'acceleration', 'agility', 'balance', 'jumping', 'natural_fitness', 'pace', 'stamina', 'strength',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->integer('instance_id')->index();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->date('dob')->nullable();
            $table->string('country_code', 10);
            $table->index(['instance_id', 'last_name', 'first_name']);
        });
        Schema::create('players', function (Blueprint $table) {

            $allPlayerFields = array_merge(
                self::TECHNICAL_FIELDS,
                self::MENTAL_FIELDS,
                self::PHYSICAL_FIELDS
            );

            $table->increments('id');
            $table->integer('instance_id')->index('instance_id');
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->integer('club_id')->nullable();
            $table->integer('loan_club_id')->nullable();
            $table->integer('contract_id')->unsigned()->index('player_contract')->nullable();
            $table->integer('value')->nullable();
            $table->integer('marketing_rank');
            $table->integer('potential');
            $table->integer('max_potential');
            $table->integer('ambition');
            $table->integer('loyalty');
            $table->string('position');
            $table->integer('technical')->nullable();
            $table->integer('current_technical_potential')->default(0);
            $table->integer('mental')->nullable();
            $table->integer('current_mental_potential')->default(0);
            $table->integer('physical')->nullable();
            $table->integer('current_physical_potential')->default(0);
            $table->boolean('is_retired')->default(false);
            $table->date('loan_start')->nullable();
            $table->date('loan_end')->nullable();

            foreach ($allPlayerFields as $field) {
                $table->integer($field);
            }

            $table->index(['instance_id', 'is_retired', 'position', 'potential'], 'players_active_position_potential_idx');
            $table->index(['instance_id', 'is_retired', 'club_id', 'position', 'potential'], 'players_active_club_position_potential_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('players');
    }
}
