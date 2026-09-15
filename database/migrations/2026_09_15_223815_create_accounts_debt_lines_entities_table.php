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
            $table->foreignId('loan_id')->nullable()->constrained('finance_entity_loans')->cascadeOnDelete();
            $table->foreignId('game_entity_account_id')->constrained('accounts_game_entities')->cascadeOnDelete();
            $table->foreignId('club_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->date('created_at');
            $table->date('due_date');
            $table->unsignedInteger('installment_number')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('finance_transactions_entities')->nullOnDelete();

            $table->index('due_date');
            $table->unique(['loan_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_debt_lines_entities');
    }
};
