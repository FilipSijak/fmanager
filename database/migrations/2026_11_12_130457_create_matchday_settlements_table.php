<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchday_settlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('game_id')->unique();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->unsignedInteger('stadium_id');
            $table->foreign('stadium_id')->references('id')->on('stadiums')->cascadeOnDelete();
            $table->unsignedInteger('attendance');
            $table->unsignedInteger('ticket_price');
            $table->bigInteger('gate_receipts');
            $table->bigInteger('commercial_income');
            $table->bigInteger('operating_costs');
            $table->date('settled_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchday_settlements');
    }
};
