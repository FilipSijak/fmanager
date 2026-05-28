<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('news', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('season_id')->nullable();
            $table->unsignedInteger('club_id')->nullable();
            $table->unsignedInteger('competition_id')->nullable();
            $table->string('title');
            $table->text('content');
            $table->string('type');
            $table->unsignedTinyInteger('priority')->default(5);
            $table->dateTime('published_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('news');
    }
}
