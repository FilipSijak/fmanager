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
        // Every manager, AI or user, shares one reputation scale (30-200) that clubs compare against rank x 10.
        Schema::table('staff_coaching', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reputation')->default(50)->after('knowledge_potential');
        });
        DB::table('staff_coaching')->whereNotNull('coaching_potential')->update(['reputation' => DB::raw('LEAST(coaching_potential, 200)')]);

        Schema::table('managers', function (Blueprint $table): void {
            $table->dropColumn('reputation');
        });

        Schema::create('manager_job_applications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->foreign('instance_id')->references('id')->on('instances')->cascadeOnDelete();
            $table->unsignedInteger('club_id');
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            $table->unsignedInteger('staff_coaching_id');
            $table->foreign('staff_coaching_id')->references('id')->on('staff_coaching')->cascadeOnDelete();
            $table->date('applied_on');
            $table->string('status', 20);
            $table->index(['instance_id', 'club_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manager_job_applications');

        Schema::table('managers', function (Blueprint $table): void {
            $table->integer('reputation')->default(50);
        });

        Schema::table('staff_coaching', function (Blueprint $table): void {
            $table->dropColumn('reputation');
        });
    }
};
