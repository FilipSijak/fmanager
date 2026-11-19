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
        Schema::table('transfers', function (Blueprint $table): void {
            $table->date('financially_settled_on')->nullable();
        });

        Schema::table('accounts_debt_lines', function (Blueprint $table): void {
            $table->unsignedInteger('transfer_id')->nullable();
            $table->unsignedInteger('installment_number')->nullable();
            $table->date('paid_on')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unique(['transfer_id', 'installment_number']);
            $table->index(['paid_on', 'due_date']);
        });

        DB::table('transfers')->where('transfer_status', 14)->update([
            'financially_settled_on' => DB::raw('COALESCE(transfer_date, offer_date, (SELECT instance_date FROM instances WHERE instances.id = transfers.instance_id))'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_debt_lines', function (Blueprint $table): void {
            $table->dropUnique(['transfer_id', 'installment_number']);
            $table->dropIndex(['paid_on', 'due_date']);
            $table->dropColumn(['transfer_id', 'installment_number', 'paid_on', 'transaction_id']);
        });

        Schema::table('transfers', function (Blueprint $table): void {
            $table->dropColumn('financially_settled_on');
        });
    }
};
