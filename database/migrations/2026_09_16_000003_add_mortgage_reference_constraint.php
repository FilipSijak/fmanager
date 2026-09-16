<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE finance_entity_loans
            ADD CONSTRAINT finance_entity_loans_mortgage_reference_check
            CHECK (
                (
                    loan_type = 'mortgage'
                    AND (
                        (stadium_stand_construction_id IS NOT NULL AND stadium_commercial_venue_id IS NULL)
                        OR (stadium_stand_construction_id IS NULL AND stadium_commercial_venue_id IS NOT NULL)
                    )
                )
                OR (
                    loan_type <> 'mortgage'
                    AND stadium_stand_construction_id IS NULL
                    AND stadium_commercial_venue_id IS NULL
                )
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE finance_entity_loans DROP CHECK finance_entity_loans_mortgage_reference_check',
        );
    }
};
