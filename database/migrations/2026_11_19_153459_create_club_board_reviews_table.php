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
        Schema::create('club_board_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_season_expectation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('staff_coaching_id')->nullable();
            $table->foreign('staff_coaching_id')->references('id')->on('staff_coaching')->nullOnDelete();
            $table->date('reviewed_on');
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('played');
            $table->unsignedSmallInteger('effective_tolerance');
            $table->string('outcome');
            $table->string('reason')->nullable();
            $table->unique(['club_season_expectation_id', 'reviewed_on'], 'club_board_review_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_board_reviews');
    }
};
