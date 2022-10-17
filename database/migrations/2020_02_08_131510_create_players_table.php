<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlayersTable extends Migration
{
    private const TEHNICAL_FIELDS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick', 'heading', 'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking', 'tackling', 'technique',
    ];

    private const MENTAL_FIELDS = [
        'aggression', 'anticipation', 'bravery', 'composure', 'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership', 'of_the_ball', 'positioning', 'teamwork', 'workrate',
    ];

    private const PHYSICAL_FILDS = [
        'acceleration', 'agility', 'balance', 'jumping', 'natural_fitness', 'pace', 'stamina', 'strength',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {

            $allPlayerFields = array_merge(
                self::TEHNICAL_FIELDS,
                self::MENTAL_FIELDS,
                self::PHYSICAL_FILDS
            );

            $table->increments('id');
            $table->integer('game_id');
            $table->integer('club_id')->nullable();
            $table->integer('value')->nullable();
            $table->string('first_name', 30);
            $table->string('last_name', 30);
            $table->integer('potential');
            $table->string('position');
            $table->string('country_code');
            $table->date('dob')->nullable();
            $table->integer('technical')->nullable();
            $table->integer('mental')->nullable();
            $table->integer('physical')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();

            foreach ($allPlayerFields as $field) {
                $table->integer($field);
            }
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
