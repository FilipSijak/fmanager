<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffTable extends Migration
{
    private const COACHING = [
        'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical', 'working_with_youngsters',
    ];

    private const MENTAL = [
        'adaptability', 'determination', 'discipline', 'man_management', 'motivating',
    ];

    private const KNOWLEDGE = [
        'judging_player_potential', 'judging_player_ability', 'judging_staff_ability', 'negotiating', 'tactics',
    ];

    private const GOALKEEPING = [
        'distribution', 'handling', 'shot_stopping',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->id();
            $table->date('contract_start');
            $table->date('contract_end');
            $table->unsignedInteger('salary');
            $table->unsignedInteger('signing_fee')->nullable();
        });

        Schema::create('staff_coaching', function (Blueprint $table) {
            $attributesFields = array_merge(
                self::COACHING,
                self::MENTAL,
                self::KNOWLEDGE,
                self::GOALKEEPING
            );

            $table->increments('id');
            $table->integer('instance_id');
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedInteger('club_id')->nullable();
            $table->foreign('club_id')->references('id')->on('clubs')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('staff_contracts')->nullOnDelete();
            $table->string('type');
            $table->integer('coaching_potential')->nullable();
            $table->integer('mental_potential')->nullable();
            $table->integer('goalkeeping_potential')->nullable();
            $table->integer('knowledge_potential')->nullable();
            $table->boolean('is_retired')->default(false);

            foreach ($attributesFields as $field) {
                $table->integer($field);
            }

            $table->index(['instance_id', 'is_retired']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_coaching');
        Schema::dropIfExists('staff_contracts');
    }
}
