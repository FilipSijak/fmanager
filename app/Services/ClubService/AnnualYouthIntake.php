<?php

namespace App\Services\ClubService;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Models\Instance;
use App\Repositories\ClubRepository;
use App\Services\ClubService\Domain\YouthIntake;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsTypes\YouthIntakeNews;
use App\Services\PersonService\GeneratePeople\PersonDetailsGenerator;
use App\Services\PersonService\GeneratePeople\PlayerCreator;
use App\Services\PlayerService\PlayerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnnualYouthIntake
{
    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly PlayerService $playerService,
        private readonly PlayerCreator $playerCreator,
        private readonly YouthIntake $youthIntake,
        private readonly PersonDetailsGenerator $personDetailsGenerator,
        private readonly NewsService $news,
        private readonly YouthIntakeNews $youthIntakeNews,
        private readonly PlayerSearchIndexDispatcher $playerSearch,
    ) {}

    public function process(Instance $instance): void
    {
        DB::transaction(function () use ($instance): void {
            $season = $this->clubRepository->youthIntakeSeason((int) $instance->id, (int) $instance->season_id);

            if ($season->youth_intake_completed_on !== null) {
                return;
            }

            $date = CarbonImmutable::parse($instance->instance_date)->startOfDay();
            $hasGeneratedPlayers = false;

            foreach ($this->clubRepository->clubsForYouthIntake((int) $instance->id) as $club) {
                $generatedPlayers = [];

                foreach ($this->youthIntake->profiles((int) $club->rank_academy) as $profile) {
                    $generatedPlayers[] = $this->playerCreator->create(
                        $profile,
                        (int) $instance->id,
                        $date,
                        $this->personDetailsGenerator->generateYouth($club->country_code, $date),
                    );
                }

                $players = $this->playerService->registerGeneratedPlayers((int) $instance->id, $club, $generatedPlayers);
                $hasGeneratedPlayers = true;

                if ((int) $club->id === (int) $instance->club_id) {
                    $players->load('person');
                    $this->news->publish($this->youthIntakeNews->report($instance, $club, $players));
                }
            }

            $this->clubRepository->completeYouthIntake($season, $date->toDateString());

            if ($hasGeneratedPlayers) {
                $this->playerSearch->reindexInstance((int) $instance->id);
            }
        });
    }
}
