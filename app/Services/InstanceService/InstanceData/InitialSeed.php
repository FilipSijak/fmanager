<?php

namespace App\Services\InstanceService\InstanceData;

use App\Models\Account;
use App\Models\BaseData\BaseClubs;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCompetitions;
use App\Models\BaseData\BaseStadiums;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Stadium;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumType;
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
        $stadiums = [];

        foreach ($baseStadiums as $baseStadium) {
            $stadium = new Stadium;

            $stadium->name = $baseStadium->name;
            $stadium->instance_id = $instanceId;
            $stadium->country_code = $baseStadium->countryCode;
            $stadium->city_id = $baseStadium->cityId;
            $stadium->capacity = $baseStadium->capacity;
            $baseClub = $baseClubs->firstWhere('stadium_id', $baseStadium->id);
            $stadiumType = $baseClub === null
                ? StadiumType::fromCapacity($baseStadium->capacity)
                : StadiumType::fromClubRank((int) $baseClub->rank);
            $stadium->type = $stadiumType;
            $stadium->commercial_limit = $stadiumType->commercialLimit();

            $stadiums[] = $stadium->toArray();
        }

        DB::table('stadiums')->insert($stadiums);
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
