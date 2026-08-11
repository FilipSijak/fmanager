<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->boolean('is_retired')->default(false)->after('contract_end');
            $table->index(['instance_id', 'is_retired']);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropIndex(['instance_id', 'is_retired']);
            $table->dropColumn('is_retired');
        });
    }
};
