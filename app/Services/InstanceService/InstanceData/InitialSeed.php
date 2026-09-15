<?php

namespace App\Services\InstanceService\InstanceData;

use App\Models\Account;
use App\Models\BaseData\BaseClubs;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCompetitions;
use App\Models\BaseData\BaseStadiums;
use App\Models\BaseData\BaseStadiumStandCapacityLimit;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Stadium;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumType;
use App\StadiumStandStatus;
use Illuminate\Support\Facades\DB;

class InitialSeed
{
    public function seedFromBaseTables(int $instanceId): void
    {
        $this->seedClubsFromBaseTable($instanceId);
        $this->seedStadiumsFromBaseTable($instanceId);
        $this->seedCommercialVenues($instanceId);
        $this->seedCompetitionsFromBaseTable($instanceId);
    }

    public function seedClubsFromBaseTable(int $instanceId): void
    {
        $baseClubs = BaseClubs::all();
        $clubs = [];

        foreach ($baseClubs as $baseClub) {
            $club = new Club;

            $club->name = $baseClub->name;
            $club->instance_id = $instanceId;
            $club->country_code = $baseClub->country_code;
            $club->city_id = $baseClub->city_id;
            $club->stadium_id = $baseClub->stadium_id;
            $club->rank = $baseClub->rank;
            $club->rank_academy = $baseClub->rank_academy;
            $club->rank_training = $baseClub->rank_training;
            $club->financial_rank = $baseClub->financial_rank;
            $club->base_club_id = $baseClub->id;

            $clubs[] = $club->toArray();
        }

        DB::table('clubs')->insert($clubs);

        $clubs = Club::where('instance_id', $instanceId)->get();

        foreach ($clubs as $club) {
            $account = new Account;
            $account->club_id = $club->id;

            $baseClub = $baseClubs->firstWhere('id', $club->base_club_id);
            $account->balance = $baseClub->starting_balance;
            $account->future_balance = $account->balance;
            $account->allowed_debt = $baseClub->allowed_debt;
            $account->transfer_budget = $baseClub->transfer_budget;
            $account->salaries_yearly_budget = $baseClub->salaries_yearly_budget;

            $account->save();
        }
    }

    public function seedStadiumsFromBaseTable(int $instanceId): void
    {
        $baseStadiums = BaseStadiums::all();
        $baseClubs = BaseClubs::all();
        $standCapacityLimits = BaseStadiumStandCapacityLimit::query()
            ->orderBy('id')
            ->get(['stadium_type', 'position', 'maximum_capacity'])
            ->groupBy('stadium_type');
        $stands = [];

        foreach ($baseStadiums as $baseStadium) {
            $baseClub = $baseClubs->firstWhere('stadium_id', $baseStadium->id);
            $stadiumType = $baseClub === null
                ? StadiumType::fromCapacity($baseStadium->capacity)
                : StadiumType::fromClubRank((int) $baseClub->rank);
            $capacity = $this->capacityForType((int) $baseStadium->capacity, $stadiumType);

            $stadiumId = DB::table('stadiums')->insertGetId([
                'name' => $baseStadium->name,
                'instance_id' => $instanceId,
                'country_code' => $baseStadium->countryCode,
                'city_id' => $baseStadium->cityId,
                'capacity' => $capacity,
                'active_capacity' => $capacity,
                'type' => $stadiumType->value,
                'commercial_limit' => $stadiumType->commercialLimit(),
            ]);

            $remainingCapacity = $capacity;
            foreach ($standCapacityLimits->get($stadiumType->value, collect()) as $limit) {
                $standCapacity = min($remainingCapacity, (int) $limit->maximum_capacity);

                $stands[] = [
                    'stadium_id' => $stadiumId,
                    'position' => $limit->position,
                    'capacity' => $standCapacity,
                    'status' => $standCapacity > 0 ? StadiumStandStatus::ACTIVE->value : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $remainingCapacity -= $standCapacity;
            }
        }

        if ($stands !== []) {
            DB::table('stadium_stands')->insert($stands);
        }
    }

    private function capacityForType(int $capacity, StadiumType $type): int
    {
        $minimumCapacity = match ($type) {
            StadiumType::VILLAGE => 1000,
            StadiumType::LOCAL => StadiumType::VILLAGE->maximumCapacity() + 1,
            StadiumType::REGIONAL => StadiumType::LOCAL->maximumCapacity() + 1,
            StadiumType::GLOBAL => StadiumType::REGIONAL->maximumCapacity() + 1,
        };
        $boundedCapacity = min(max($capacity, $minimumCapacity), $type->maximumCapacity());

        return min(
            $type->maximumCapacity(),
            (int) (ceil($boundedCapacity / 1000) * 1000),
        );
    }

    private function seedCommercialVenues(int $instanceId): void
    {
        $baseClubs = BaseClubs::query()->get(['stadium_id']);
        $baseStadiums = BaseStadiums::query()
            ->whereIn('id', $baseClubs->pluck('stadium_id')->unique())
            ->get()
            ->keyBy('id');
        $stadiums = Stadium::query()
            ->where('instance_id', $instanceId)
            ->get()
            ->keyBy(fn (Stadium $stadium): string => $stadium->name);
        $categoryIds = BaseCommercialCategory::query()->pluck('id');
        $venueSizes = collect(CommercialVenueSize::cases());

        foreach ($baseClubs as $baseClub) {
            $baseStadium = $baseStadiums->get($baseClub->stadium_id);

            if ($baseStadium === null) {
                continue;
            }

            $stadium = $stadiums->get($baseStadium->name);

            if ($stadium === null) {
                continue;
            }

            $allowedCategoryIds = DB::table('base_commercial_category_stadium_type')
                ->where('stadium_type', $stadium->type->value)
                ->pluck('category_id')
                ->intersect($categoryIds);
            $existingCategoryIds = DB::table('stadium_commercial_venues')
                ->where('instance_id', $instanceId)
                ->where('stadium_id', $stadium->id)
                ->pluck('category_id');
            $availableCategoryIds = $allowedCategoryIds->diff($existingCategoryIds)->shuffle();
            $targetVenueCount = intdiv((int) $stadium->commercial_limit, 2);
            $venueCount = min($targetVenueCount - $existingCategoryIds->count(), $availableCategoryIds->count());

            if ($venueCount <= 0) {
                continue;
            }

            DB::table('stadium_commercial_venues')->insert(
                $availableCategoryIds->take($venueCount)->map(fn (int $categoryId): array => [
                    'instance_id' => $instanceId,
                    'stadium_id' => $stadium->id,
                    'category_id' => $categoryId,
                    'size' => $venueSizes->random()->value,
                ])->all()
            );
        }
    }

    public function seedCompetitionsFromBaseTable(int $instanceId): void
    {
        $baseCompetitions = BaseCompetitions::all();
        $competitions = [];

        foreach ($baseCompetitions as $baseCompetition) {
            $competition = new Competition;

            $competition->name = $baseCompetition->name;
            $competition->country_code = $baseCompetition->country_code;
            $competition->instance_id = $instanceId;
            $competition->rank = $baseCompetition->rank;
            $competition->type = $baseCompetition->type;
            $competition->groups = $baseCompetition->groups;
            $competition->clubs_number = $baseCompetition->clubs_number;
            $competition->competition_scope = $baseCompetition->competition_scope;
            $competition->continent = $baseCompetition->continent;
            $competition->continental_tier = $baseCompetition->continental_tier;
            $competition->base_competition_id = $baseCompetition->id;

            $competitions[] = $competition->toArray();
        }

        DB::table('competitions')->insert($competitions);
    }
}
