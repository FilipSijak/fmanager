<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_game_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->string('name')->unique();
            $table->unsignedTinyInteger('rank')->default(10);
            $table->unsignedBigInteger('starting_balance');
            $table->index(['type', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_game_entities');
    }
};
