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
            $table->integer('appearance')->unsigned();
            $table->integer('clean_sheet')->unsigned();
            $table->integer('goal')->unsigned();
            $table->integer('assist')->unsigned();
            $table->integer('league')->unsigned();
            $table->integer('promotion')->unsigned();
            $table->integer('cup')->unsigned();
            $table->integer('el')->unsigned();
            $table->integer('cl')->unsigned();
            $table->integer('pc_salary_raise')->unsigned();
            $table->integer('pc_demotion_pay_cut')->unsigned();
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
