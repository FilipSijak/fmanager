<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsorship_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('season_id');
            $table->foreign('season_id')->references('id')->on('seasons')->cascadeOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained('finance_transactions_entities')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->date('paid_on');
            $table->timestamps();

            $table->unique(['sponsorship_contract_id', 'season_id'], 'sponsorship_payment_per_season_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_payments');
    }
};
