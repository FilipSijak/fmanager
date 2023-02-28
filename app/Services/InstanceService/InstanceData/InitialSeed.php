<?php

namespace App\Services\InstanceService\InstanceData;

use App\Models\BaseData\BaseClubs;
use App\Models\BaseData\BaseCompetitions;
use App\Models\BaseData\BaseStadiums;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Stadium;
use Illuminate\Support\Facades\DB;

class InitialSeed
{
    public function seedFromBaseTables(int $instanceId): void
    {
        $this->seedClubsFromBaseTable($instanceId);
        $this->seedStadiumsFromBaseTable($instanceId);
        $this->seedCompetitionsFromBaseTable($instanceId);
    }

    public function seedClubsFromBaseTable(int $instanceId): void
    {
        $baseClubs = BaseClubs::all();
        $clubs     = [];

        foreach ($baseClubs as $baseClub) {
            $club = new Club();

            $club->name          = $baseClub->name;
            $club->instance_id   = $instanceId;
            $club->country_code  = $baseClub->country_code;
            $club->city_id       = $baseClub->city_id;
            $club->stadium_id    = $baseClub->stadium_id;
            $club->rank          = $baseClub->rank;
            $club->rank_academy  = $baseClub->rank_academy;
            $club->rank_training = $baseClub->rank_training;

            $clubs[] = $club->toArray();
        }

        DB::table('clubs')->insert($clubs);
    }

    public function seedStadiumsFromBaseTable(int $instanceId): void
    {
        $baseStadiums = BaseStadiums::all();
        $stadiums     = [];

        foreach ($baseStadiums as $baseStadium) {
            $stadium = new Stadium();

            $stadium->name         = $baseStadium->name;
            $stadium->instance_id  = $instanceId;
            $stadium->country_code = $baseStadium->countryCode;
            $stadium->city_id      = $baseStadium->cityId;
            $stadium->capacity     = $baseStadium->capacity;

            $stadiums[] = $stadium->toArray();
        }

        DB::table('stadiums')->insert($stadiums);
    }

    public function seedCompetitionsFromBaseTable(int $instanceId): void
    {
        $baseCompetitions = BaseCompetitions::all();
        $competitions     = [];

        foreach ($baseCompetitions as $baseCompetition) {
            $competition = new Competition();

            $competition->name         = $baseCompetition->name;
            $competition->country_code = $baseCompetition->country_code;
            $competition->instance_id  = $instanceId;
            $competition->rank         = $baseCompetition->rank;
            $competition->type         = $baseCompetition->type;
            $competition->groups       = $baseCompetition->groups;
            $competition->clubs_number = $baseCompetition->clubs_number;

            $competitions[] = $competition->toArray();
        }

        DB::table('competitions')->insert($competitions);
    }
}
