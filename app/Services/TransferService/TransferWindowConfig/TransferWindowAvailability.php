<?php

namespace App\Services\TransferService\TransferWindowConfig;

use Carbon\Carbon;

class TransferWindowAvailability
{
    public function isTransferWindowOpen(string $instanceDate): bool
    {
        $currentYear = Carbon::createFromFormat('Y-m-d', $instanceDate)->year;

        return (
            $instanceDate >= $currentYear . '-' . TransferWindowConfig::SUMMER_WINDOW_START &&
            $instanceDate <= $currentYear . '-' . TransferWindowConfig::SUMMER_WINDOW_END
        ) || (
            $instanceDate >= $currentYear . '-' . TransferWindowConfig::WINTER_WINDOW_START &&
            $instanceDate <= $currentYear . '-' . TransferWindowConfig::WINTER_WINDOW_END
        );
    }

    public function nextAvailableTransferWindow(string $instanceDate): string
    {
        $instanceYear = Carbon::createFromFormat('Y-m-d', $instanceDate)->year;

        if ($instanceDate > $instanceYear . '-' . TransferWindowConfig::SUMMER_WINDOW_END) {
            ++$instanceYear;

            return $instanceYear . '-' . TransferWindowConfig::WINTER_WINDOW_START;
        } else {
            return $instanceYear . '-' . TransferWindowConfig::SUMMER_WINDOW_START;
        }
    }
}
