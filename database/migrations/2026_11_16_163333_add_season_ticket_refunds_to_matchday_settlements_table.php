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
        Schema::table('matchday_settlements', function (Blueprint $table): void {
            $table->unsignedInteger('season_ticket_refund_quantity')->default(0);
            $table->bigInteger('season_ticket_refund_amount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matchday_settlements', function (Blueprint $table): void {
            $table->dropColumn(['season_ticket_refund_quantity', 'season_ticket_refund_amount']);
        });
    }
};
