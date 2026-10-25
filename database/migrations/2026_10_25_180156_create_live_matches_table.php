<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_matches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('game_id')->unique();
            $table->json('state');
            $table->timestamps();

            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_matches');
    }
};
