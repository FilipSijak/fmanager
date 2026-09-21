<?php

namespace Database\Seeders;

use App\Models\BaseData\BaseFormation;
use Illuminate\Database\Seeder;

class TacticsSeeder extends Seeder
{
    public function run(): void
    {
        $formations = [
            ['code' => '5-3-2', 'name' => '5-3-2', 'tactical_tendency' => 'attacking', 'slots' => [
                ['position' => 'GK', 'x' => 50, 'y' => 90], ['position' => 'CB', 'x' => 32, 'y' => 74], ['position' => 'CB', 'x' => 50, 'y' => 76], ['position' => 'CB', 'x' => 68, 'y' => 74], ['position' => 'LWB', 'x' => 10, 'y' => 62, 'has_arrow' => true], ['position' => 'RWB', 'x' => 90, 'y' => 62, 'has_arrow' => true], ['position' => 'CM', 'x' => 30, 'y' => 48, 'has_arrow' => true], ['position' => 'CM', 'x' => 70, 'y' => 48, 'has_arrow' => true], ['position' => 'AM', 'x' => 50, 'y' => 32, 'has_arrow' => true], ['position' => 'ST', 'x' => 36, 'y' => 14], ['position' => 'ST', 'x' => 64, 'y' => 14],
            ]],
            ['code' => '4-4-2', 'name' => '4-4-2', 'tactical_tendency' => 'balanced', 'slots' => [
                ['position' => 'GK', 'x' => 50, 'y' => 90], ['position' => 'LB', 'x' => 15, 'y' => 74], ['position' => 'CB', 'x' => 38, 'y' => 78], ['position' => 'CB', 'x' => 62, 'y' => 78], ['position' => 'RB', 'x' => 85, 'y' => 74], ['position' => 'LM', 'x' => 15, 'y' => 50, 'has_arrow' => true], ['position' => 'CM', 'x' => 38, 'y' => 52], ['position' => 'CM', 'x' => 62, 'y' => 52], ['position' => 'RM', 'x' => 85, 'y' => 50, 'has_arrow' => true], ['position' => 'ST', 'x' => 36, 'y' => 20], ['position' => 'ST', 'x' => 64, 'y' => 20],
            ]],
            ['code' => '4-3-3', 'name' => '4-3-3', 'tactical_tendency' => 'attacking', 'slots' => [
                ['position' => 'GK', 'x' => 50, 'y' => 90], ['position' => 'LB', 'x' => 15, 'y' => 74], ['position' => 'CB', 'x' => 38, 'y' => 78], ['position' => 'CB', 'x' => 62, 'y' => 78], ['position' => 'RB', 'x' => 85, 'y' => 74], ['position' => 'CM', 'x' => 30, 'y' => 54, 'has_arrow' => true], ['position' => 'CM', 'x' => 50, 'y' => 58], ['position' => 'CM', 'x' => 70, 'y' => 54, 'has_arrow' => true], ['position' => 'LW', 'x' => 20, 'y' => 22], ['position' => 'AM', 'x' => 50, 'y' => 18, 'has_arrow' => true], ['position' => 'RW', 'x' => 80, 'y' => 22],
            ]],
            ['code' => '3-5-2', 'name' => '3-5-2', 'tactical_tendency' => 'balanced', 'slots' => [
                ['position' => 'GK', 'x' => 50, 'y' => 90], ['position' => 'CB', 'x' => 30, 'y' => 76], ['position' => 'CB', 'x' => 50, 'y' => 80], ['position' => 'CB', 'x' => 70, 'y' => 76], ['position' => 'LWB', 'x' => 8, 'y' => 56, 'has_arrow' => true], ['position' => 'RWB', 'x' => 92, 'y' => 56, 'has_arrow' => true], ['position' => 'CM', 'x' => 30, 'y' => 44], ['position' => 'CM', 'x' => 50, 'y' => 48], ['position' => 'CM', 'x' => 70, 'y' => 44, 'has_arrow' => true], ['position' => 'ST', 'x' => 38, 'y' => 16], ['position' => 'ST', 'x' => 62, 'y' => 16],
            ]],
        ];

        foreach ($formations as $formationData) {
            $formation = BaseFormation::query()->updateOrCreate(
                ['code' => $formationData['code']],
                ['name' => $formationData['name'], 'is_active' => true, 'tactical_tendency' => $formationData['tactical_tendency']],
            );
            $formation->slots()->delete();
            $formation->slots()->createMany(array_map(
                fn (array $slot, int $index): array => ['slot' => (string) ($index + 1), ...$slot],
                $formationData['slots'],
                array_keys($formationData['slots']),
            ));
        }
    }
}
