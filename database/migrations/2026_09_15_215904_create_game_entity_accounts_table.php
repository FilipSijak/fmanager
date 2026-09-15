<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_game_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_entity_id')->constrained('game_entities')->cascadeOnDelete();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('future_balance')->default(0);
            $table->bigInteger('allowed_debt')->default(0);
            $table->timestamps();

            $table->unique(['game_entity_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_game_entities');
    }
};
