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
            $table->date('contract_start');
            $table->date('contract_end');
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
            $table->decimal('pc_promotion_salary_raise')->unsigned()->nullable();
            $table->decimal('pc_demotion_salary_cut')->unsigned()->nullable();
            $table->integer('loan_contribution_pc')->unsigned()->default(0);
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
