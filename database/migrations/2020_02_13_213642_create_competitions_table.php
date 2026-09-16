<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompetitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('instance_id');
            $table->string('name');
            $table->string('country_code');
            $table->integer('rank');
            $table->string('type');
            $table->integer('groups')->nullable()->default(null);
            $table->integer('clubs_number');
            $table->string('competition_scope')->default('domestic');
            $table->string('continent')->nullable();
            $table->unsignedTinyInteger('continental_tier')->nullable();
            $table->unique(['instance_id', 'continent', 'continental_tier'], 'competition_continental_tier_unique');
            $table->integer('base_competition_id')->unsigned()->nullable();
            $table->index(['instance_id', 'base_competition_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competitions');
    }
}
