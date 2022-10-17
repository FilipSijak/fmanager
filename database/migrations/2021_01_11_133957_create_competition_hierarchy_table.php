<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompetitionHierarchyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('competition_hierarchy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('competition_id');
            $table->integer('parent_competition_id')->nullable();
            $table->integer('child_competition_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_hierarchy');
    }
}
