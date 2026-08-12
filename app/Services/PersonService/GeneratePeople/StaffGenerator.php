<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\PersonTypes;
use Faker\Factory as FakerFactory;
use Faker\Generator;

class StaffGenerator
{
    private const COACHING_ATTRIBUTES = [
        'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
        'working_with_youngsters', 'adaptability', 'determination', 'discipline',
        'man_management', 'motivating', 'judging_player_potential', 'judging_player_ability',
        'judging_staff_ability', 'negotiating', 'tactics', 'distribution', 'handling', 'shot_stopping',
    ];

    private const SCOUT_ATTRIBUTES = [
        'judging_player_ability', 'judging_player_potential', 'tactical_knowledge',
        'data_analysis', 'market_knowledge',
    ];

    private const PHYSIO_ATTRIBUTES = [
        'physiotherapy', 'injury_prevention', 'rehabilitation', 'sports_science', 'fitness_assessment',
    ];

    private Generator $faker;

    public function __construct(private readonly StaffPotential $staffPotential)
    {
        $this->faker = FakerFactory::create();
    }

    public function generateForClubRank(int $rank): array
    {
        return array_map(function (\stdClass $staffPotential): \stdClass {
            $staffMember = new \stdClass;
            $staffMember->role = $staffPotential->role;
            $staffMember->potential = $staffPotential->potential;
            $staffMember->first_name = $this->faker->firstNameMale;
            $staffMember->last_name = $this->faker->lastName;
            $staffMember->dob = $this->faker->dateTimeBetween('-65 years', '-28 years')->format('Y-m-d');
            $staffMember->country_code = $this->faker->countryCode;
            $staffMember->attributes = $this->generateAttributes(
                $staffPotential->potential,
                $this->attributeNamesForRole($staffPotential->role)
            );

            return $staffMember;
        }, $this->staffPotential->getStaffPotentialAndRole($rank));
    }

    private function attributeNamesForRole(string $role): array
    {
        if (in_array($role, PersonTypes::COACHING_ROLES, true)) {
            return self::COACHING_ATTRIBUTES;
        }

        return $role === PersonTypes::SCOUT ? self::SCOUT_ATTRIBUTES : self::PHYSIO_ATTRIBUTES;
    }

    private function generateAttributes(int $potential, array $attributeNames): array
    {
        return array_combine(
            $attributeNames,
            array_map(fn (): int => $this->staffAttribute($potential), $attributeNames)
        );
    }

    private function staffAttribute(int $potential): int
    {
        $ability = (int) round($potential / 10);

        return rand(max(1, $ability - 3), min(20, $ability + 2));
    }
}
