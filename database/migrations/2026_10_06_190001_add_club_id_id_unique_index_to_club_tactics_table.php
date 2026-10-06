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
        Schema::table('club_tactics', function (Blueprint $table): void {
            $table->unique(['club_id', 'id'], 'club_tactics_club_id_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_tactics', function (Blueprint $table): void {
            $table->dropUnique('club_tactics_club_id_id_unique');
        });
    }
};
