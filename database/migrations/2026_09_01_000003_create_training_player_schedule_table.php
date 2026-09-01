<?php

use App\Services\TrainingService\TrainingIntensity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_player_schedule', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('player_id');
            $table->unsignedTinyInteger('training_category_id');
            $table->unsignedTinyInteger('intensity')->default(TrainingIntensity::Medium->value);
            $table->timestamps();

            $table->unique(
                ['player_id', 'training_category_id'],
                'training_player_category_unique'
            );
            $table->foreign('player_id')
                ->references('id')
                ->on('players')
                ->cascadeOnDelete();
            $table->foreign('training_category_id')
                ->references('id')
                ->on('training_categories')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE training_player_schedule ADD CONSTRAINT training_player_intensity_check CHECK (`intensity` BETWEEN 0 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('training_player_schedule');
    }
};
