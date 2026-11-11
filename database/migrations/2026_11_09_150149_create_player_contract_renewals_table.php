<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_contract_renewals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('player_id');
            $table->unsignedInteger('club_id');
            $table->unsignedInteger('contract_id');
            $table->string('status', 20);
            $table->unsignedSmallInteger('months');
            $table->date('previous_contract_end');
            $table->date('contract_end');
            $table->date('offered_on');
            $table->json('terms');
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->index(['instance_id', 'player_id', 'offered_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_contract_renewals');
    }
};
