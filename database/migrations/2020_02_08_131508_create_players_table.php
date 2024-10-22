<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
        Schema::create('players', function (Blueprint $table) {

            $allPlayerFields = array_merge(
                self::TECHNICAL_FIELDS,
                self::MENTAL_FIELDS,
                self::PHYSICAL_FIELDS
            );

            $table->increments('id');
            $table->integer('instance_id')->index('instance_id');
            $table->integer('club_id')->nullable();
            $table->integer('player_contract_id')->unsigned()->index('player_contract')->nullable();
            $table->integer('value')->nullable();
            $table->string('first_name', 30);
            $table->string('last_name', 30);
            $table->integer('marketing_rank');
            $table->integer('potential');
            $table->integer('ambition');
            $table->integer('loyalty');
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
