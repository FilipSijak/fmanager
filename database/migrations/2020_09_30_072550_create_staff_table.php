<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStaffTable extends Migration
{
    private const COACHING = [
        'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical', 'working_with_youngsters'
    ];

    private const MENTAL = [
        'adaptability', 'determination', 'discipline', 'man_management', 'motivating'
    ];

    private const KNOWLEDGE = [
        'judging_player_potential', 'judging_player_ability', 'judging_staff_ability', 'negotiating', 'tactics'
    ];

    private const GOALKEEPING = [
        'distribution', 'handling', 'shot_stopping'
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff', function (Blueprint $table) {
            $attributesFields = array_merge(
                self::COACHING,
                self::MENTAL,
                self::KNOWLEDGE,
                self::GOALKEEPING
            );

            $table->increments('id');
            $table->integer('game_id');
            $table->string('type');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('dob');
            $table->string('country_code');
            $table->integer('coaching_potential')->nullable();
            $table->integer('mental_potential')->nullable();
            $table->integer('goalkeeping_potential')->nullable();
            $table->integer('knowledge_potential')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();

            foreach ($attributesFields as $field) {
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
        Schema::dropIfExists('staff');
    }
}
