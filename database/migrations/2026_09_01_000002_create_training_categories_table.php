<?php

use App\Services\TrainingService\TrainingCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_categories', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 30)->unique();
        });

        DB::table('training_categories')->insert(
            array_map(
                static fn (TrainingCategory $category): array => [
                    'id' => $category->value,
                    'name' => $category->name,
                ],
                TrainingCategory::cases()
            )
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('training_categories');
    }
};
