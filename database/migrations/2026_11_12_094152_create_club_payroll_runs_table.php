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
        Schema::create('club_payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->date('paid_on');
            $table->bigInteger('player_wages');
            $table->bigInteger('staff_wages');
            $table->unique(['club_id', 'paid_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_payroll_runs');
    }
};
