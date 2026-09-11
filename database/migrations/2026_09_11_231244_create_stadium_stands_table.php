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
        Schema::create('stadium_stands', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('stadium_id');
            $table->foreign('stadium_id')->references('id')->on('stadiums')->cascadeOnDelete();
            $table->string('position', 20);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 30)->nullable();
            $table->timestamps();

            $table->unique(['stadium_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stadium_stands');
    }
};
