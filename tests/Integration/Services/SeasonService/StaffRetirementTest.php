<?php

namespace Tests\Integration\Services\SeasonService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Models\StaffCoaching;
use App\Models\StaffContract;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Repositories\StaffRepository;
use App\Services\SeasonService\StaffRetirement;
use App\Services\SeasonService\StaffRetirementDecision;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffRetirementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_retires_selected_staff_across_all_staff_tables(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2027-06-16',
        ]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $asOfDate = CarbonImmutable::parse($instance->instance_date);

        $age59 = $this->staffMember(StaffCoaching::class, $club, $asOfDate->subYears(59));
        $age60 = $this->staffMember(StaffCoaching::class, $club, $asOfDate->subYears(60));
        $age67 = $this->staffMember(StaffScout::class, $club, $asOfDate->subYears(67));
        $age75 = $this->staffMember(StaffPhysio::class, $club, $asOfDate->subYears(75));

        $rolls = collect([1, 10000, 10000]);
        $service = new StaffRetirement(
            app(StaffRepository::class),
            new StaffRetirementDecision(fn (): int => (int) $rolls->shift())
        );

        $this->assertSame(2, $service->retireEligibleStaff($instance));

        foreach ([$age60, $age75] as $retiredStaff) {
            $this->assertDatabaseHas($retiredStaff->getTable(), [
                'id' => $retiredStaff->id,
                'is_retired' => true,
                'club_id' => null,
                'contract_id' => null,
            ]);
            $this->assertDatabaseMissing('staff_contracts', ['id' => $retiredStaff->contract_id]);
        }

        foreach ([$age59, $age67] as $activeStaff) {
            $this->assertDatabaseHas($activeStaff->getTable(), [
                'id' => $activeStaff->id,
                'is_retired' => false,
                'club_id' => $club->id,
                'contract_id' => $activeStaff->contract_id,
            ]);
            $this->assertDatabaseHas('staff_contracts', [
                'id' => $activeStaff->contract_id,
                'contract_start' => '2026-07-01',
                'contract_end' => '2028-06-30',
            ]);
        }
    }

    private function staffMember(string $model, Club $club, CarbonImmutable $dob)
    {
        $person = Person::factory()->create([
            'instance_id' => $club->instance_id,
            'dob' => $dob->toDateString(),
        ]);

        $contract = StaffContract::query()->create([
            'contract_start' => '2026-07-01',
            'contract_end' => '2028-06-30',
            'salary' => 10000,
            'signing_fee' => null,
        ]);

        return $model::factory()->create([
            'instance_id' => $club->instance_id,
            'person_id' => $person->id,
            'club_id' => $club->id,
            'contract_id' => $contract->id,
            'is_retired' => false,
        ]);
    }
}
