<?php

namespace Tests\Unit;

use App\Services\CommercialService\CommercialRankingConfig;
use PHPUnit\Framework\TestCase;

class CommercialRankingConfigTest extends TestCase
{
    public function test_it_normalizes_each_rank_scale(): void
    {
        $this->assertSame(0.5, CommercialRankingConfig::normalizeCountryRank(50));
        $this->assertSame(0.75, CommercialRankingConfig::normalizeCompetitionRank(7500));
        $this->assertSame(0.5, CommercialRankingConfig::normalizeClubRank(10));
    }

    public function test_it_clamps_ranks_to_the_normalized_range(): void
    {
        $this->assertSame(0.0, CommercialRankingConfig::normalizeCountryRank(-1));
        $this->assertSame(1.0, CommercialRankingConfig::normalizeCompetitionRank(10001));
        $this->assertSame(1.0, CommercialRankingConfig::normalizeClubRank(21));
    }
}
