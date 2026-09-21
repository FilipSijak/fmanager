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
        Schema::create('club_tactics', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreignId('base_formation_id')->constrained('base_formations')->restrictOnDelete();
            $table->string('name', 100)->default('Default tactic');
            $table->string('mentality', 20);
            $table->string('pressing', 20);
            $table->string('passing', 20);
            $table->timestamps();
            $table->unique('club_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_tactics');
    }
};
