<?php

namespace Tests\Unit\Person;

use App\Services\ClubService\SquadAnalysis\SquadStaffConfig;
use App\Services\PersonService\GeneratePeople\StaffPotential;
use App\Services\PersonService\GeneratePeople\StaffPotentialData;
use App\Services\PersonService\PersonConfig\PersonTypes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffPotentialTest extends TestCase
{
    #[Test]
    public function it_generates_the_configured_staff_roles_from_club_rank(): void
    {
        $staff = (new StaffPotential)->getStaffPotentialAndRole(15);

        $this->assertCount(20, $staff);
        $this->assertCount(SquadStaffConfig::MANAGER_COUNT, $this->withRole($staff, PersonTypes::MANAGER));
        $this->assertCount(SquadStaffConfig::ASSISTANT_MANAGER_COUNT, $this->withRole($staff, PersonTypes::ASSISTANT_MANAGER));
        $this->assertCount(SquadStaffConfig::FIRST_TEAM_COACH_COUNT, $this->withRole($staff, PersonTypes::COACH));
        $this->assertCount(SquadStaffConfig::YOUTH_TEAM_COACH_COUNT, $this->withRole($staff, PersonTypes::YOUTH_COACH));
        $this->assertCount(SquadStaffConfig::SCOUT_COUNT, $this->withRole($staff, PersonTypes::SCOUT));
        $this->assertCount(SquadStaffConfig::PHYSIO_FIRST_TEAM_COUNT, $this->withRole($staff, PersonTypes::PHYSIO));
        $this->assertCount(SquadStaffConfig::PHYSIO_YOUTH_TEAM_COUNT, $this->withRole($staff, PersonTypes::YOUTH_PHYSIO));

        foreach ($staff as $staffMember) {
            $this->assertSame(15, $staffMember->rank);
            $this->assertPotentialMatchesRankAndRole($staffMember);
        }
    }

    #[Test]
    public function free_staff_receive_independent_random_ranks(): void
    {
        $staffPotential = new StaffPotential;
        $staff = [];

        for ($i = 0; $i < 200; $i++) {
            $staff[] = $staffPotential->getRandomStaffPotential();
        }

        $ranks = array_map(fn (StaffPotentialData $member): int => $member->rank, $staff);

        $this->assertGreaterThan(1, count(array_unique($ranks)));
        $this->assertContainsOnly('int', $ranks);

        foreach ($staff as $staffMember) {
            $this->assertGreaterThanOrEqual(1, $staffMember->rank);
            $this->assertLessThanOrEqual(20, $staffMember->rank);
            $this->assertPotentialMatchesRankAndRole($staffMember);
        }
    }

    /** @param list<StaffPotentialData> $staff */
    private function withRole(array $staff, string $role): array
    {
        return array_values(array_filter(
            $staff,
            fn (StaffPotentialData $staffMember): bool => $staffMember->role === $role
        ));
    }

    private function assertPotentialMatchesRankAndRole(StaffPotentialData $staff): void
    {
        $rank = $staff->rank * 10;

        if ($staff->role === PersonTypes::MANAGER) {
            [$minimum, $maximum] = [$rank, min(200, $rank + 20)];
        } elseif ($staff->role === PersonTypes::ASSISTANT_MANAGER) {
            [$minimum, $maximum] = [$rank - 20, $rank];
        } elseif ($staff->role === PersonTypes::YOUTH_COACH) {
            [$minimum, $maximum] = [$rank - 35, $rank - 10];
        } else {
            [$minimum, $maximum] = [$rank - 15, $rank + 5];
        }

        $this->assertGreaterThanOrEqual(max(30, $minimum), $staff->potential);
        $this->assertLessThanOrEqual(min(200, max(30, $maximum)), $staff->potential);
    }
}
