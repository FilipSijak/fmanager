<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('finances', function (Blueprint $table) {
            $table->id();
            $table->integer('club_id')->unsigned()->index('club_id');
            $table->string('finance_type');
            $table->integer('finance_type_account_id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('club_finances');
    }
};
