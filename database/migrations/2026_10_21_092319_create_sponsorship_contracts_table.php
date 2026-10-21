<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreignId('sponsor_account_id')->constrained('accounts_game_entities')->restrictOnDelete();
            $table->unsignedInteger('annual_amount');
            $table->date('starts_at');
            $table->date('expires_at');
            $table->timestamps();

            $table->unique(['club_id', 'starts_at']);
            $table->index(['instance_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_contracts');
    }
};
