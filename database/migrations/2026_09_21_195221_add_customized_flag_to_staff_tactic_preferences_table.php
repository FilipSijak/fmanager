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
        Schema::table('staff_tactic_preferences', function (Blueprint $table): void {
            $table->boolean('is_customized')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_tactic_preferences', function (Blueprint $table): void {
            $table->dropColumn('is_customized');
        });
    }
};
