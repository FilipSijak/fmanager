<?php

namespace Tests\Integration\Services\MatchEngineService;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\ClubTactic;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Services\MatchEngineService\MatchTeamBuilder;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchTeamBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const LINEUP_POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    #[Test]
    public function an_average_4_4_2_rates_average_chance_creation(): void
    {
        $club = $this->clubWithSavedLineup([]);

        $team = app(MatchTeamBuilder::class)->forClub($club->id, '2026-08-15');

        $this->assertSame(10.0, round($team->chanceCreation, 2));
    }

    /** @return array<string, array{list<string>, float}> */
    public static function strongCreators(): array
    {
        return [
            'midfielders create the most' => [['LM', 'CM', 'RM'], 16.56],
            'forwards create fewer' => [['ST'], 11.97],
            'wing backs create fewer still' => [['LB', 'RB'], 11.15],
            'centre backs create the least' => [['CB'], 10.33],
        ];
    }

    #[Test]
    #[DataProvider('strongCreators')]
    public function strong_creators_add_chance_creation_by_the_position_they_play(array $strongPositions, float $chanceCreation): void
    {
        $club = $this->clubWithSavedLineup($strongPositions);

        $team = app(MatchTeamBuilder::class)->forClub($club->id, '2026-08-15');

        $this->assertSame($chanceCreation, round($team->chanceCreation, 2));
    }

    /**
     * A club whose saved 4-4-2 has every attribute at 10, except players in the given positions, who are at 20.
     *
     * @param  list<string>  $strongPositions
     */
    private function clubWithSavedLineup(array $strongPositions): Club
    {
        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        Instance::factory()->create(['id' => 1, 'season_id' => 1]);
        $club = Club::factory()->create(['instance_id' => 1]);
        $tactic = ClubTactic::query()->create([
            'club_id' => $club->id,
            'base_formation_id' => BaseFormation::query()->create(['code' => '4-4-2', 'name' => '4-4-2', 'is_active' => true])->id,
            'name' => 'Manager tactic',
            'mentality' => Mentality::BALANCED,
            'pressing' => PressingIntensity::MEDIUM,
            'passing' => PassingStyle::MIXED,
        ]);
        $attributes = [...PlayerFields::TECHNICAL_FIELDS, ...PlayerFields::MENTAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS, ...PlayerFields::GOALKEEPING_FIELDS];

        foreach (self::LINEUP_POSITIONS as $index => $position) {
            $player = Player::factory()->create([
                ...array_fill_keys($attributes, in_array($position, $strongPositions, true) ? 20 : 10),
                'instance_id' => 1,
                'club_id' => $club->id,
                'position' => $position,
            ]);
            ClubTacticPlayerSlot::query()->create([
                'instance_id' => 1,
                'club_id' => $club->id,
                'club_tactic_id' => $tactic->id,
                'player_id' => $player->id,
                'slot' => (string) ($index + 1),
                'position' => $position,
            ]);
        }

        return $club;
    }
}
