<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Person;
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

    /** @return list<GeneratedStaffData> */
    public function generateForClubRank(int $rank): array
    {
        return array_map(
            fn (StaffPotentialData $staffPotential): GeneratedStaffData => $this->generateStaffMember($staffPotential),
            $this->staffPotential->getStaffPotentialAndRole($rank)
        );
    }

    /** @return list<GeneratedStaffData> */
    public function generateFreeStaff(int $count): array
    {
        $staffMembers = [];

        for ($i = 0; $i < $count; $i++) {
            $staffMembers[] = $this->generateStaffMember($this->staffPotential->getRandomStaffPotential());
        }

        return $staffMembers;
    }

    public function generateFromFormerPlayer(Person $person): GeneratedStaffData
    {
        $staffPotential = $this->staffPotential->getRandomFormerPlayerStaffPotential();

        return new GeneratedStaffData(
            role: $staffPotential->role,
            potential: $staffPotential->potential,
            rank: $staffPotential->rank,
            firstName: $person->first_name,
            lastName: $person->last_name,
            dateOfBirth: $person->dob->toDateString(),
            countryCode: $person->country_code,
            attributes: $this->generateAttributes(
                $staffPotential->potential,
                $this->attributeNamesForRole($staffPotential->role)
            ),
        );
    }

    private function generateStaffMember(StaffPotentialData $staffPotential): GeneratedStaffData
    {
        return new GeneratedStaffData(
            role: $staffPotential->role,
            potential: $staffPotential->potential,
            rank: $staffPotential->rank,
            firstName: $this->faker->firstNameMale(),
            lastName: $this->faker->lastName(),
            dateOfBirth: $this->faker->dateTimeBetween('-65 years', '-28 years')->format('Y-m-d'),
            countryCode: $this->faker->countryCode(),
            attributes: $this->generateAttributes(
                $staffPotential->potential,
                $this->attributeNamesForRole($staffPotential->role)
            ),
        );
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
