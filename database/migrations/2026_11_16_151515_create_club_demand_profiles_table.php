<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('club_demand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->date('period_start');
            $table->integer('club_rank');
            $table->integer('country_rank');
            $table->decimal('performance_score', 8, 6);
            $table->decimal('ticket_price_sensitivity', 8, 6);
            $table->decimal('commercial_price_sensitivity', 8, 6);
            $table->unique(['club_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_demand_profiles');
    }
};
