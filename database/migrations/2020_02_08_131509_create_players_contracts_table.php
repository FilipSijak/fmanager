<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('players_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('salary')->unsigned();
            $table->integer('appearance')->unsigned()->nullable();
            $table->integer('clean_sheet')->unsigned()->nullable();
            $table->integer('goal')->unsigned()->nullable();
            $table->integer('assist')->unsigned()->nullable();
            $table->integer('league')->unsigned()->nullable();
            $table->integer('promotion')->unsigned()->nullable();
            $table->integer('cup')->unsigned()->nullable();
            $table->integer('el')->unsigned()->nullable();
            $table->integer('cl')->unsigned()->nullable();
            $table->decimal('pc_salary_raise')->unsigned()->nullable();
            $table->decimal('pc_demotion_pay_cut')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('players_contracts');
    }
};
