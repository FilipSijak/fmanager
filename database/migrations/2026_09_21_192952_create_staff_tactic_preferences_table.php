<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_tactic_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('staff_coaching_id');
            $table->foreign('staff_coaching_id')->references('id')->on('staff_coaching')->cascadeOnDelete();
            $table->unique('staff_coaching_id');
            $table->foreignId('base_formation_id')->constrained('base_formations')->restrictOnDelete();
            $table->string('mentality', 20);
            $table->string('pressing', 20);
            $table->string('passing', 20);
            $table->timestamps();
        });

        $formationId = DB::table('base_formations')->where('is_active', true)->orderBy('id')->value('id');

        if ($formationId !== null) {
            $staffIds = DB::table('staff_coaching')->pluck('id');

            foreach ($staffIds as $staffId) {
                DB::table('staff_tactic_preferences')->insert([
                    'staff_coaching_id' => $staffId,
                    'base_formation_id' => $formationId,
                    'mentality' => 'balanced',
                    'pressing' => 'medium',
                    'passing' => 'mixed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_tactic_preferences');
    }
};
