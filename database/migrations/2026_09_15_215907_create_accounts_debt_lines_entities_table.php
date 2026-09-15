<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_debt_lines_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_entity_account_id')->constrained('accounts_game_entities')->cascadeOnDelete();
            $table->foreignId('club_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->date('created_at');
            $table->date('due_date');

            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_debt_lines_entities');
    }
};
