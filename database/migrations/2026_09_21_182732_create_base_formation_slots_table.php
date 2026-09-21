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
        Schema::create('base_formation_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('base_formation_id')->constrained('base_formations')->cascadeOnDelete();
            $table->string('slot', 20);
            $table->string('position', 20);
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->boolean('has_arrow')->default(false);
            $table->unique(['base_formation_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_formation_slots');
    }
};
