<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->unsignedInteger('season_id');
            $table->foreign('season_id')->references('id')->on('seasons')->cascadeOnDelete();
            $table->foreignId('sponsor_account_id')->constrained('accounts_game_entities')->restrictOnDelete();
            $table->foreignId('accepted_contract_id')->nullable()->unique()->constrained('sponsorship_contracts')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('active_club_id')->nullable()->virtualAs("CASE WHEN status = 'pending' THEN club_id ELSE NULL END");
            $table->unique('active_club_id');
            $table->unsignedTinyInteger('attempt_number');
            $table->unsignedTinyInteger('term_seasons');
            $table->unsignedInteger('annual_amount');
            $table->unsignedInteger('maximum_annual_amount');
            $table->unsignedInteger('negotiation_rounds')->default(0);
            $table->string('negotiation_response', 30)->nullable();
            $table->date('offered_on');
            $table->date('starts_at');
            $table->date('expires_at');
            $table->date('respond_by');
            $table->timestamps();

            $table->unique(['club_id', 'sponsor_account_id', 'season_id', 'attempt_number'], 'sponsorship_offer_attempt_unique');
            $table->index(['instance_id', 'status', 'respond_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_offers');
    }
};
