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
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->integer('sending_account_id')->nullable()->change();
            $table->integer('receiving_account_id')->nullable()->change();
            $table->string('category')->nullable();
            $table->string('reference')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('finance_transactions')->whereNull('sending_account_id')->orWhereNull('receiving_account_id')->exists()) {
            throw new RuntimeException('Cannot roll back while income or expense postings exist.');
        }

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['category', 'reference']);
            $table->integer('sending_account_id')->nullable(false)->change();
            $table->integer('receiving_account_id')->nullable(false)->change();
        });
    }
};
