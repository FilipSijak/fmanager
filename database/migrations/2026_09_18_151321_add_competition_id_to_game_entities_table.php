<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_entities', function (Blueprint $table): void {
            $table->foreignId('competition_id')->nullable()->after('instance_id')->constrained('competitions')->cascadeOnDelete();
            $table->unique(['instance_id', 'competition_id'], 'game_entities_competition_unique');
        });

        DB::table('instances')->pluck('id')->each(function (int $instanceId): void {
            DB::table('competitions')
                ->where('instance_id', $instanceId)
                ->get(['id', 'name'])
                ->each(function (object $competition) use ($instanceId): void {
                    $gameEntityId = DB::table('game_entities')->insertGetId([
                        'instance_id' => $instanceId,
                        'competition_id' => $competition->id,
                        'type' => 'competition',
                        'name' => $competition->name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('accounts_game_entities')->insert([
                        'game_entity_id' => $gameEntityId,
                        'instance_id' => $instanceId,
                        'balance' => 10_000_000_000,
                        'future_balance' => 10_000_000_000,
                        'allowed_debt' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        Schema::table('game_entities', function (Blueprint $table): void {
            $table->dropUnique('game_entities_competition_unique');
            $table->dropForeign(['competition_id']);
            $table->dropColumn('competition_id');
        });
    }
};
