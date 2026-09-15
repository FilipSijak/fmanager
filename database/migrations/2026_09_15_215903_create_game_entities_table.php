<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->timestamps();

            $table->index(['instance_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_entities');
    }
};
