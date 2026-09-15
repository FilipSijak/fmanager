<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_transactions_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_entity_account_id')->constrained('accounts_game_entities')->cascadeOnDelete();
            $table->foreignId('club_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('direction', 30);
            $table->string('event_type', 30);
            $table->unsignedBigInteger('event_id')->nullable();
            $table->bigInteger('amount');
            $table->dateTime('transaction_date');
            $table->timestamps();

            $table->index(['event_type', 'event_id']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions_entities');
    }
};
