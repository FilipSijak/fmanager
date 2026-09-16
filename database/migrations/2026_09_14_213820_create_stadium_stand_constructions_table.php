<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stadium_stand_constructions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('stadium_id');
            $table->unsignedBigInteger('stadium_stand_id');
            $table->unsignedInteger('target_capacity');
            $table->unsignedInteger('capacity_increase');
            $table->date('started_at');
            $table->date('completes_at');
            $table->date('completed_at')->nullable();
            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->foreign('stadium_id')->references('id')->on('stadiums')->cascadeOnDelete();
            $table->foreign('stadium_stand_id')->references('id')->on('stadium_stands')->cascadeOnDelete();
            $table->index(['instance_id', 'completes_at'], 'ssc_instance_completes_at_index');
            $table->foreign(['instance_id', 'stadium_id'], 'ssc_instance_stadium_foreign')->references(['instance_id', 'id'])->on('stadiums')->cascadeOnDelete();
            $table->foreign(['stadium_id', 'stadium_stand_id'], 'ssc_stadium_stand_foreign')->references(['stadium_id', 'id'])->on('stadium_stands')->cascadeOnDelete();
            $table->unique('stadium_stand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stadium_stand_constructions');
    }
};
