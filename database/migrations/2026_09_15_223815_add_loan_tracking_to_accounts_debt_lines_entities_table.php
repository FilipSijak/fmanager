<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_debt_lines_entities', function (Blueprint $table): void {
            $table->foreignId('loan_id')
                ->nullable()
                ->after('id')
                ->constrained('finance_entity_loans')
                ->cascadeOnDelete();
            $table->unsignedInteger('installment_number')->nullable()->after('amount');
            $table->dateTime('paid_at')->nullable()->after('due_date');
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('paid_at')
                ->constrained('finance_transactions_entities')
                ->nullOnDelete();

            $table->unique(['loan_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::table('accounts_debt_lines_entities', function (Blueprint $table): void {
            $table->dropForeign(['loan_id']);
            $table->dropForeign(['transaction_id']);
            $table->dropUnique(['loan_id', 'installment_number']);
            $table->dropColumn(['loan_id', 'installment_number', 'paid_at', 'transaction_id']);
        });
    }
};
