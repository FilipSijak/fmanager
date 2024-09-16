<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('injury_types', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('duration_from');
            $table->integer('duration_to');
        });
    }

    public function down()
    {
        Schema::dropIfExists('injury_types');
    }
};
