<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchday_settlements', function (Blueprint $table): void {
            $table->string('ticket_type', 20)->nullable();
            $table->unsignedInteger('season_ticket_attendance')->default(0);
            $table->unsignedInteger('paying_attendance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matchday_settlements', function (Blueprint $table): void {
            $table->dropColumn(['ticket_type', 'season_ticket_attendance', 'paying_attendance']);
        });
    }
};
