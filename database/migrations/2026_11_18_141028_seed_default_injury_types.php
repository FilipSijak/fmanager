<?php

use Database\Seeders\InjuryTypesSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new InjuryTypesSeeder)->run();
    }

    public function down(): void
    {
        /** Injury definitions are retained because saved injury histories reference them. */
    }
};
