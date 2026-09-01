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
        Schema::create('training_intensities', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 30)->unique();
        });

        DB::table('training_intensities')->insert(
            array_map(
                static fn (TrainingIntensity $intensity): array => [
                    'id' => $intensity->value,
                    'name' => $intensity->name,
                ],
                TrainingIntensity::cases()
            )
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('training_intensities');
    }
};
