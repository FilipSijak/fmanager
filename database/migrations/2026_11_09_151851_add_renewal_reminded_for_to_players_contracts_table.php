<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players_contracts', function (Blueprint $table): void {
            $table->date('renewal_reminded_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('players_contracts', function (Blueprint $table): void {
            $table->dropColumn('renewal_reminded_for');
        });
    }
};
