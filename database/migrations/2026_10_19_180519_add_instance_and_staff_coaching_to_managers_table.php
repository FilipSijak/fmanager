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
        Schema::table('managers', function (Blueprint $table): void {
            $table->unsignedBigInteger('instance_id')->after('user_id');
            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->unique('instance_id');
            $table->unsignedInteger('staff_coaching_id')->after('instance_id');
            $table->foreign('staff_coaching_id')->references('id')->on('staff_coaching')->cascadeOnDelete();
            $table->unique('staff_coaching_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table): void {
            $table->dropForeign(['instance_id']);
            $table->dropForeign(['staff_coaching_id']);
            $table->dropUnique(['instance_id']);
            $table->dropUnique(['staff_coaching_id']);
            $table->dropColumn(['instance_id', 'staff_coaching_id']);
        });
    }
};
