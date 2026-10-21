<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_entities', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rank')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('game_entities', function (Blueprint $table): void {
            $table->dropColumn('rank');
        });
    }
};
