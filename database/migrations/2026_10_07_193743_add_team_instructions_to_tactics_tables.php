<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PLAYER_ROLE_COLUMNS = [
        'free_kicks_left_player_id',
        'free_kicks_right_player_id',
        'corners_left_player_id',
        'corners_right_player_id',
        'playmaker_player_id',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_tactic_preferences', function (Blueprint $table): void {
            $this->addStyleColumns($table);
        });

        Schema::table('club_tactics', function (Blueprint $table): void {
            $this->addStyleColumns($table);

            // Player roles are club-specific, so they live only on the club tactic and
            // never on the manager's preference, which follows the manager between clubs.
            foreach (self::PLAYER_ROLE_COLUMNS as $column) {
                $table->unsignedInteger($column)->nullable();
                $table->foreign($column)->references('id')->on('players')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_tactics', function (Blueprint $table): void {
            foreach (self::PLAYER_ROLE_COLUMNS as $column) {
                $table->dropForeign([$column]);
            }

            $table->dropColumn([...self::PLAYER_ROLE_COLUMNS, 'tackling', 'offside_trap', 'counter_attack', 'men_behind_ball']);
        });

        Schema::table('staff_tactic_preferences', function (Blueprint $table): void {
            $table->dropColumn(['tackling', 'offside_trap', 'counter_attack', 'men_behind_ball']);
        });
    }

    private function addStyleColumns(Blueprint $table): void
    {
        $table->string('tackling', 20)->default('normal');
        $table->boolean('offside_trap')->default(false);
        $table->boolean('counter_attack')->default(false);
        $table->boolean('men_behind_ball')->default(false);
    }
};
