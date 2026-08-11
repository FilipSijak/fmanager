<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IDENTITY_COLUMNS = ['first_name', 'last_name', 'dob', 'country_code'];

    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->integer('instance_id')->index();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->date('dob')->nullable();
            $table->string('country_code', 10);
            $table->index(['instance_id', 'last_name', 'first_name']);
        });

        foreach (['players', 'staff'] as $careerTable) {
            Schema::table($careerTable, function (Blueprint $table): void {
                $table->foreignId('person_id')
                    ->nullable()
                    ->after('instance_id')
                    ->constrained('people')
                    ->restrictOnDelete();
            });

            DB::table($careerTable)
                ->select(['id', 'instance_id', ...self::IDENTITY_COLUMNS])
                ->orderBy('id')
                ->chunkById(200, function ($careers) use ($careerTable): void {
                    foreach ($careers as $career) {
                        $personId = DB::table('people')->insertGetId([
                            'instance_id' => $career->instance_id,
                            'first_name' => $career->first_name,
                            'last_name' => $career->last_name,
                            'dob' => $career->dob,
                            'country_code' => $career->country_code,
                        ]);

                        DB::table($careerTable)
                            ->where('id', $career->id)
                            ->update(['person_id' => $personId]);
                    }
                });
        }

        foreach (['players', 'staff'] as $careerTable) {
            Schema::table($careerTable, function (Blueprint $table): void {
                $table->dropForeign(['person_id']);
            });

            Schema::table($careerTable, function (Blueprint $table): void {
                $table->unsignedBigInteger('person_id')->nullable(false)->change();
                $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            });
        }

        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(self::IDENTITY_COLUMNS);
        });

        Schema::table('staff', function (Blueprint $table): void {
            $table->dropColumn(self::IDENTITY_COLUMNS);
        });
    }

    public function down(): void
    {
        foreach (['players', 'staff'] as $careerTable) {
            Schema::table($careerTable, function (Blueprint $table): void {
                $table->string('first_name', 50)->nullable();
                $table->string('last_name', 50)->nullable();
                $table->date('dob')->nullable();
                $table->string('country_code', 10)->nullable();
            });

            DB::table($careerTable)
                ->join('people', $careerTable.'.person_id', '=', 'people.id')
                ->update([
                    $careerTable.'.first_name' => DB::raw('people.first_name'),
                    $careerTable.'.last_name' => DB::raw('people.last_name'),
                    $careerTable.'.dob' => DB::raw('people.dob'),
                    $careerTable.'.country_code' => DB::raw('people.country_code'),
                ]);

            Schema::table($careerTable, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('person_id');
            });
        }

        Schema::dropIfExists('people');
    }
};
