<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBaseCompetitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('base_competitions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('country_code');
            $table->integer('rank');
            $table->string('type');
            $table->integer('groups')->nullable();
            $table->integer('clubs_number');
            $table->string('competition_scope')->default('domestic');
            $table->string('continent')->nullable();
            $table->unsignedTinyInteger('continental_tier')->nullable();
            $table->unique(['continent', 'continental_tier'], 'base_continental_tier_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('base_competitions');
    }
}
