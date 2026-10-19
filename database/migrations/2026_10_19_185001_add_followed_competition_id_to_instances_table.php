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
        Schema::table('instances', function (Blueprint $table): void {
            // The league the user follows; kept after they leave a club so an unemployed manager still follows it.
            $table->unsignedInteger('followed_competition_id')->nullable()->after('club_id');
            $table->foreign('followed_competition_id')->references('id')->on('competitions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table): void {
            $table->dropForeign(['followed_competition_id']);
            $table->dropColumn('followed_competition_id');
        });
    }
};
