<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_entity_loans', function (Blueprint $table): void {
            $table->string('loan_type', 20)->default('cash')->after('borrower_club_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_entity_loans', function (Blueprint $table): void {
            $table->dropColumn('loan_type');
        });
    }
};
