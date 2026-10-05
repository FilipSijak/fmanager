<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            foreach (['handling', 'reflexes', 'one_on_ones', 'aerial_reach', 'distribution'] as $field) {
                $table->unsignedTinyInteger($field)->default(1);
            }
        });

        Schema::table('players_progress', function (Blueprint $table): void {
            foreach (['handling', 'reflexes', 'one_on_ones', 'aerial_reach', 'distribution'] as $field) {
                $table->unsignedSmallInteger($field)->default(50);
            }
        });

        DB::table('positions')->updateOrInsert(
            ['id' => 15],
            ['name' => 'Goalkeeper', 'alias' => 'GK']
        );
    }

    public function down(): void
    {
        DB::table('positions')->where('id', 15)->where('alias', 'GK')->delete();

        Schema::table('players_progress', function (Blueprint $table): void {
            $table->dropColumn(['handling', 'reflexes', 'one_on_ones', 'aerial_reach', 'distribution']);
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(['handling', 'reflexes', 'one_on_ones', 'aerial_reach', 'distribution']);
        });
    }
};
