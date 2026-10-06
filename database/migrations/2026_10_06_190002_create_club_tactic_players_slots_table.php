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
        Schema::create('club_tactic_players_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('club_id');
            $table->foreignId('club_tactic_id')->constrained('club_tactics')->cascadeOnDelete();
            $table->unsignedInteger('player_id');
            // Starting-XI slots store the formation's own slot id (e.g. "1".."11", matching
            // base_formation_slots.slot for whichever formation the club currently uses).
            // Substitute slots ("SUB1".."SUB7") are not part of any formation, so this is a
            // plain string rather than a foreign key to base_formation_slots.
            $table->string('slot', 10);
            $table->string('position', 20);
            $table->timestamps();

            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();

            // No composite FK chaining instance_id to club_id here: clubs.instance_id is a
            // plain signed integer with no FK of its own to instances.id (type-incompatible
            // with instances.id's bigIncrements), so that invariant isn't DB-enforceable
            // without altering the existing clubs column - same as every other table that
            // references clubs.instance_id today.
            $table->foreign(['club_id', 'club_tactic_id'], 'ctps_club_tactic_foreign')
                ->references(['club_id', 'id'])->on('club_tactics')->cascadeOnDelete();

            $table->index('instance_id');
            $table->unique(['club_tactic_id', 'slot'], 'ctps_tactic_slot_unique');
            $table->unique(['club_tactic_id', 'player_id'], 'ctps_tactic_player_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_tactic_players_slots');
    }
};
