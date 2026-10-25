<?php

namespace Tests\Integration\Services\SquadLineupService;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\SquadLineupService\AiLineupSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiLineupSelectorTest extends TestCase
{
    use RefreshDatabase;

    private const MATCH_DATE = '2026-08-15';

    private const FORMATION_POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    #[Test]
    public function fills_each_formation_slot_with_the_highest_potential_player_for_it_and_picks_a_bench(): void
    {
        $club = $this->clubWithManager();
        $goalkeeper = $this->player($club, 'GK', 80);
        $backupGoalkeeper = $this->player($club, 'GK', 20);
        $leftBack = $this->player($club, 'LB', 70);
        $rightBack = $this->player($club, 'RB', 70);
        [$firstCentreBack, $secondCentreBack] = [$this->player($club, 'CB', 90), $this->player($club, 'CB', 80)];
        $reserveCentreBacks = [$this->player($club, 'CB', 50), $this->player($club, 'CB', 45), $this->player($club, 'CB', 44), $this->player($club, 'CB', 43), $this->player($club, 'CB', 42)];
        [$firstMidfielder, $secondMidfielder, $reserveMidfielder] = [$this->player($club, 'CM', 85), $this->player($club, 'CM', 75), $this->player($club, 'CM', 40)];
        $defensiveMidfielder = $this->player($club, 'DMC', 65);
        $attackingMidfielder = $this->player($club, 'AMC', 60);
        [$firstStriker, $secondStriker] = [$this->player($club, 'ST', 88), $this->player($club, 'ST', 77)];
        $this->player($club, 'ST', 30);

        app(AiLineupSelector::class)->selectForClub($club, self::MATCH_DATE);

        $this->assertSame([
            ['1', 'GK', $goalkeeper->id],
            ['2', 'LB', $leftBack->id],
            ['3', 'CB', $firstCentreBack->id],
            ['4', 'CB', $secondCentreBack->id],
            ['5', 'RB', $rightBack->id],
            ['6', 'LM', $defensiveMidfielder->id],
            ['7', 'CM', $firstMidfielder->id],
            ['8', 'CM', $secondMidfielder->id],
            ['9', 'RM', $attackingMidfielder->id],
            ['10', 'ST', $firstStriker->id],
            ['11', 'ST', $secondStriker->id],
            ['SUB1', 'SUB', $backupGoalkeeper->id],
            ['SUB2', 'SUB', $reserveCentreBacks[0]->id],
            ['SUB3', 'SUB', $reserveCentreBacks[1]->id],
            ['SUB4', 'SUB', $reserveCentreBacks[2]->id],
            ['SUB5', 'SUB', $reserveCentreBacks[3]->id],
            ['SUB6', 'SUB', $reserveCentreBacks[4]->id],
            ['SUB7', 'SUB', $reserveMidfielder->id],
        ], $this->savedLineup($club));
    }

    #[Test]
    public function leaves_out_players_injured_on_the_match_date(): void
    {
        $club = $this->clubWithManager();
        $this->eleven($club);
        $injured = $this->player($club, 'CB', 95);
        $recovered = $this->player($club, 'CB', 90);
        $this->injure($injured, '2026-08-10', self::MATCH_DATE);
        $this->injure($recovered, '2026-08-01', '2026-08-14');

        app(AiLineupSelector::class)->selectForClub($club, self::MATCH_DATE);

        $this->assertDatabaseMissing('club_tactic_players_slots', ['player_id' => $injured->id]);
        $this->assertDatabaseHas('club_tactic_players_slots', ['slot' => '3', 'player_id' => $recovered->id]);
    }

    #[Test]
    public function fills_a_slot_nobody_plays_with_an_outfield_player_rather_than_a_spare_goalkeeper(): void
    {
        $club = $this->clubWithManager();
        $this->eleven($club, without: 'ST');
        $this->player($club, 'GK', 99);
        $centreBack = $this->player($club, 'CB', 10);

        app(AiLineupSelector::class)->selectForClub($club, self::MATCH_DATE);

        $this->assertDatabaseHas('club_tactic_players_slots', ['slot' => '11', 'position' => 'ST', 'player_id' => $centreBack->id]);
    }

    #[Test]
    public function clears_the_saved_lineup_when_fewer_than_11_players_are_fit(): void
    {
        $club = $this->clubWithManager();
        $players = $this->eleven($club);
        app(AiLineupSelector::class)->selectForClub($club, self::MATCH_DATE);
        $this->injure($players[0], self::MATCH_DATE, self::MATCH_DATE);

        app(AiLineupSelector::class)->selectForClub($club, self::MATCH_DATE);

        $this->assertDatabaseCount('club_tactic_players_slots', 0);
    }

    #[Test]
    public function leaves_a_club_without_a_manager_to_the_match_engine(): void
    {
        $club = $this->club();
        $this->eleven($club);

        app(AiLineupSelector::class)->selectForClub($club, self::MATCH_DATE);

        $this->assertDatabaseCount('club_tactic_players_slots', 0);
    }

    private function club(): Club
    {
        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        Instance::factory()->create(['id' => 1, 'season_id' => 1]);
        $formation = BaseFormation::query()->create(['code' => '4-4-2', 'name' => '4-4-2', 'is_active' => true, 'tactical_tendency' => 'balanced']);

        foreach (self::FORMATION_POSITIONS as $index => $position) {
            $formation->slots()->create(['slot' => (string) ($index + 1), 'position' => $position, 'x' => 50, 'y' => 50]);
        }

        return Club::factory()->create(['instance_id' => 1]);
    }

    private function clubWithManager(): Club
    {
        $club = $this->club();
        StaffCoaching::factory()->create(['instance_id' => 1, 'club_id' => $club->id, 'type' => PersonTypes::MANAGER]);

        return $club;
    }

    /** @return list<Player> One player per formation slot, all with the same potential, leaving one slot's player out if asked. */
    private function eleven(Club $club, ?string $without = null): array
    {
        $positions = self::FORMATION_POSITIONS;

        if ($without !== null) {
            unset($positions[array_search($without, $positions, true)]);
        }

        return array_values(array_map(fn (string $position): Player => $this->player($club, $position, 50), $positions));
    }

    private function player(Club $club, string $position, int $potential): Player
    {
        return Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $club->id,
            'position' => $position,
            'potential' => $potential,
        ]);
    }

    private function injure(Player $player, string $from, string $until): void
    {
        DB::table('injuries')->insertOrIgnore(['id' => 1, 'type' => 'Knee injury', 'severity' => 4, 'duration_from' => 10, 'duration_to' => 20]);
        DB::table('player_injuries')->insert([
            'instance_id' => 1,
            'season_id' => 1,
            'player_id' => $player->id,
            'injury_id' => 1,
            'injury_start_date' => $from,
            'injury_end_date' => $until,
        ]);
    }

    /** @return list<array{string, string, int}> */
    private function savedLineup(Club $club): array
    {
        return ClubTacticPlayerSlot::query()
            ->where('club_id', $club->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ClubTacticPlayerSlot $slot): array => [$slot->slot, $slot->position, $slot->player_id])
            ->all();
    }
}
