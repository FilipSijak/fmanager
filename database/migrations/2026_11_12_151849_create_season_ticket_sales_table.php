<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_ticket_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            foreach (['club_id' => 'clubs', 'season_id' => 'seasons', 'stadium_id' => 'stadiums'] as $column => $parent) {
                $table->unsignedInteger($column);
                $table->foreign($column)->references('id')->on($parent)->cascadeOnDelete();
            }
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('home_games');
            $table->unsignedInteger('league_ticket_price');
            $table->unsignedInteger('ticket_price');
            $table->bigInteger('amount');
            $table->date('sold_on');
            $table->foreignId('transaction_id')->nullable()->constrained('finance_transactions')->restrictOnDelete();
            $table->unique(['club_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_ticket_sales');
    }
};
