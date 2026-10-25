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

    /** @return array<string, array{list<string>}> */
    public static function formations(): array
    {
        return [
            '4-4-2' => [self::LINEUP_POSITIONS],
            '4-2-3-1' => [['GK', 'LB', 'CB', 'CB', 'RB', 'DM', 'DM', 'LW', 'AM', 'RW', 'ST']],
            '4-3-3' => [['GK', 'LB', 'CB', 'CB', 'RB', 'CM', 'CM', 'CM', 'LW', 'AM', 'RW']],
            '5-3-2' => [['GK', 'CB', 'CB', 'CB', 'LWB', 'RWB', 'CM', 'CM', 'AM', 'ST', 'ST']],
            '3-5-2' => [['GK', 'CB', 'CB', 'CB', 'LWB', 'RWB', 'CM', 'CM', 'CM', 'ST', 'ST']],
        ];
    }

    #[Test]
    #[DataProvider('formations')]
    public function average_players_rate_average_chance_creation_in_any_formation(array $positions): void
    {
        $club = $this->clubWithSavedLineup([], $positions);

        $team = app(MatchTeamBuilder::class)->forClub($club->id, '2026-08-15');

        $this->assertSame(10.0, round($team->chanceCreation, 2));
    }

    #[Test]
    public function strong_midfielders_lift_a_4_2_3_1_more_than_strong_wingers_and_striker(): void
    {
        $positions = ['GK', 'LB', 'CB', 'CB', 'RB', 'DM', 'DM', 'LW', 'AM', 'RW', 'ST'];
        $strongMidfield = $this->clubWithSavedLineup(['DM', 'AM'], $positions);
        $strongAttack = $this->clubWithSavedLineup(['LW', 'RW', 'ST'], $positions, instanceId: 2);

        $builder = app(MatchTeamBuilder::class);

        $this->assertSame(15.26, round($builder->forClub($strongMidfield->id, '2026-08-15')->chanceCreation, 2));
        $this->assertSame(13.16, round($builder->forClub($strongAttack->id, '2026-08-15')->chanceCreation, 2));
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
     * A club whose saved lineup has every attribute at 10, except players in the given positions, who are at 20.
     *
     * @param  list<string>  $strongPositions
     * @param  list<string>  $positions
     */
    private function clubWithSavedLineup(array $strongPositions, array $positions = self::LINEUP_POSITIONS, int $instanceId = 1): Club
    {
        Season::factory()->create(['id' => $instanceId, 'instance_id' => $instanceId]);
        Instance::factory()->create(['id' => $instanceId, 'season_id' => $instanceId]);
        $club = Club::factory()->create(['instance_id' => $instanceId]);
        $code = implode('-', [$instanceId, ...$positions]);
        $tactic = ClubTactic::query()->create([
            'club_id' => $club->id,
            'base_formation_id' => BaseFormation::query()->create(['code' => $code, 'name' => $code, 'is_active' => true])->id,
            'name' => 'Manager tactic',
            'mentality' => Mentality::BALANCED,
            'pressing' => PressingIntensity::MEDIUM,
            'passing' => PassingStyle::MIXED,
        ]);
        $attributes = [...PlayerFields::TECHNICAL_FIELDS, ...PlayerFields::MENTAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS, ...PlayerFields::GOALKEEPING_FIELDS];

        foreach ($positions as $index => $position) {
            $player = Player::factory()->create([
                ...array_fill_keys($attributes, in_array($position, $strongPositions, true) ? 20 : 10),
                'instance_id' => $instanceId,
                'club_id' => $club->id,
                'position' => $position,
            ]);
            ClubTacticPlayerSlot::query()->create([
                'instance_id' => $instanceId,
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
