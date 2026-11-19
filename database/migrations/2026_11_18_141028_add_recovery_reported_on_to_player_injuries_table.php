<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_injuries', function (Blueprint $table): void {
            $table->date('recovery_reported_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_injuries', function (Blueprint $table): void {
            $table->dropColumn('recovery_reported_on');
        });
    }
};
