<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STAFF_TABLES = [
        'staff_coaching',
        'staff_scouts',
        'staff_physio',
    ];

    public function up(): void
    {
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->id();
            $table->date('contract_start');
            $table->date('contract_end');
            $table->unsignedInteger('salary');
            $table->unsignedInteger('signing_fee')->nullable();
        });

        Schema::table('players_contracts', function (Blueprint $table): void {
            $table->unsignedInteger('signing_fee')->nullable()->after('salary');
        });

        foreach (self::STAFF_TABLES as $staffTable) {
            Schema::table($staffTable, function (Blueprint $table): void {
                $table->foreignId('contract_id')
                    ->nullable()
                    ->after('club_id')
                    ->constrained('staff_contracts')
                    ->nullOnDelete();
            });

            DB::table($staffTable)
                ->whereNotNull('contract_start')
                ->whereNotNull('contract_end')
                ->orderBy('id')
                ->each(function (object $staff) use ($staffTable): void {
                    $contractId = DB::table('staff_contracts')->insertGetId([
                        'contract_start' => $staff->contract_start,
                        'contract_end' => $staff->contract_end,
                        'salary' => 0,
                        'signing_fee' => null,
                    ]);

                    DB::table($staffTable)->where('id', $staff->id)->update([
                        'contract_id' => $contractId,
                    ]);
                });

            Schema::table($staffTable, function (Blueprint $table): void {
                $table->dropColumn(['contract_start', 'contract_end']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::STAFF_TABLES as $staffTable) {
            Schema::table($staffTable, function (Blueprint $table): void {
                $table->date('contract_start')->nullable();
                $table->date('contract_end')->nullable();
            });

            DB::table($staffTable)
                ->whereNotNull('contract_id')
                ->orderBy('id')
                ->each(function (object $staff) use ($staffTable): void {
                    $contract = DB::table('staff_contracts')->find($staff->contract_id);

                    if ($contract) {
                        DB::table($staffTable)->where('id', $staff->id)->update([
                            'contract_start' => $contract->contract_start,
                            'contract_end' => $contract->contract_end,
                        ]);
                    }
                });

            Schema::table($staffTable, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('contract_id');
            });
        }

        Schema::table('players_contracts', function (Blueprint $table): void {
            $table->dropColumn('signing_fee');
        });

        Schema::dropIfExists('staff_contracts');
    }
};
