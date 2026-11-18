<?php

namespace App\Console\Commands;

use App\Services\CommercialService\CommercialService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InitializeStadiumCommerce extends Command
{
    protected $signature = 'commercial:initialize-stadiums {--instance= : Only initialize stadiums in this game instance}';

    protected $description = 'Fill missing stadium ticket prices and venue products without changing existing settings';

    public function handle(CommercialService $commercial): int
    {
        $option = $this->option('instance');
        $instanceId = $option === null ? null : (string) $option;
        if ($instanceId !== null && (! ctype_digit($instanceId) || (int) $instanceId <= 0)) {
            $this->error('The instance must be a positive integer.');

            return self::FAILURE;
        }
        try {
            $processed = $commercial->initializeStadiums($instanceId === null ? null : (int) $instanceId);
        } catch (ModelNotFoundException) {
            $this->error('The game instance does not exist.');

            return self::FAILURE;
        }
        $this->info("Initialized {$processed} stadiums.");

        return self::SUCCESS;
    }
}
