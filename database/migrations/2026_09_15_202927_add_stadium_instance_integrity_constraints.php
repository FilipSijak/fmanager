<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stadiums', function (Blueprint $table): void {
            $table->unsignedBigInteger('instance_id')->change();
            $table->foreign('instance_id', 'stadiums_instance_id_foreign')
                ->references('id')
                ->on('instances')
                ->cascadeOnDelete();
            $table->unique(['instance_id', 'id'], 'stadiums_instance_id_id_unique');
        });

        Schema::table('stadium_stands', function (Blueprint $table): void {
            $table->unique(['stadium_id', 'id'], 'stadium_stands_stadium_id_id_unique');
        });

        Schema::table('stadium_commercial_venues', function (Blueprint $table): void {
            $table->foreign(['instance_id', 'stadium_id'], 'scv_instance_stadium_foreign')
                ->references(['instance_id', 'id'])
                ->on('stadiums')
                ->cascadeOnDelete();
        });

        Schema::table('stadium_stand_constructions', function (Blueprint $table): void {
            $table->index(['instance_id', 'completes_at'], 'ssc_instance_completes_at_index');
            $table->foreign(['instance_id', 'stadium_id'], 'ssc_instance_stadium_foreign')
                ->references(['instance_id', 'id'])
                ->on('stadiums')
                ->cascadeOnDelete();
            $table->foreign(['stadium_id', 'stadium_stand_id'], 'ssc_stadium_stand_foreign')
                ->references(['stadium_id', 'id'])
                ->on('stadium_stands')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stadium_stand_constructions', function (Blueprint $table): void {
            $table->dropForeign('ssc_stadium_stand_foreign');
            $table->dropForeign('ssc_instance_stadium_foreign');
            $table->dropIndex('ssc_instance_completes_at_index');
        });

        Schema::table('stadium_commercial_venues', function (Blueprint $table): void {
            $table->dropForeign('scv_instance_stadium_foreign');
        });

        Schema::table('stadium_stands', function (Blueprint $table): void {
            $table->dropUnique('stadium_stands_stadium_id_id_unique');
        });

        Schema::table('stadiums', function (Blueprint $table): void {
            $table->dropForeign('stadiums_instance_id_foreign');
            $table->dropUnique('stadiums_instance_id_id_unique');
            $table->integer('instance_id')->change();
        });
    }
};
