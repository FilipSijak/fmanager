<?php

namespace App\Console\Commands;

use App\Services\CompetitionService\Progression\SeasonProgressionService;
use Illuminate\Console\Command;

class CalculateSeasonProgression extends Command
{
    protected $signature = 'season:calculate-qualifications {seasonId}';
    protected $description = 'Calculate pending promotions, relegations and continental qualifications';

    public function handle(SeasonProgressionService $service): int
    {
        $result = $service->finalize((int) $this->argument('seasonId'));
        $this->info("Created or updated {$result['movements']} movements and {$result['qualifications']} qualifications.");
        return self::SUCCESS;
    }
}
