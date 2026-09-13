<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStadiumsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stadiums', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('instance_id');
            $table->string('country_code')->nullable();
            $table->integer('city_id')->unsigned()->nullable();
            $table->integer('capacity');
            $table->unsignedInteger('active_capacity')->default(0);
            $table->string('type', 20)->default('local');
            $table->unsignedTinyInteger('commercial_limit')->default(3);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stadiums');
    }
}
