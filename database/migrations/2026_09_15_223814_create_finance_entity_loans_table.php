<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entity_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->foreignId('lender_game_entity_account_id')
                ->constrained('accounts_game_entities')
                ->restrictOnDelete();
            $table->foreignId('borrower_club_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->unsignedBigInteger('principal');
            $table->unsignedBigInteger('interest_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('installment_count');
            $table->date('started_at');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['instance_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entity_loans');
    }
};
