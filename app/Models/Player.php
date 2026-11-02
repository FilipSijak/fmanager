<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use App\Services\PersonService\Data\PersonInfo;
use App\Services\TrainingService\TrainingPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

class Player extends Model
{
    public $timestamps = false;

    protected $casts = [
        'is_retired' => 'boolean',
    ];

    use BelongsToGameInstance, HasFactory;

    protected static function booted(): void
    {
        static::created(function (Player $player): void {
            $player->initializeProgress();
        });
    }

    public function initializeProgress(): void
    {
        $now = now();

        DB::table('players_progress')->insert([
            'player_id' => $this->id,
            'instance_id' => $this->instance_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('training_player_schedule')->insert(
            array_map(
                fn (array $schedule): array => $schedule + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                TrainingPlan::defaultForPosition($this->position)->rowsForPlayers([$this->id])
            )
        );
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    private array $personIdentity = [];

    public function setPersonIdentity(PersonInfo $personDetails): void
    {
        $this->personIdentity = [
            'first_name' => $personDetails->firstName,
            'last_name' => $personDetails->lastName,
            'country_code' => $personDetails->countryCode,
            'dob' => $personDetails->dateOfBirth,
        ];
    }

    protected function firstName(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->person?->first_name ?? $this->personIdentity['first_name'] ?? null
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->person?->last_name ?? $this->personIdentity['last_name'] ?? null
        );
    }

    protected function dob(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->person?->dob?->toDateString() ?? $this->personIdentity['dob'] ?? null
        );
    }

    protected function countryCode(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->person?->country_code ?? $this->personIdentity['country_code'] ?? null
        );
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function getTableColumns()
    {
        $columns = $this->getConnection()->select(
            (new MySqlGrammar)->compileColumnListing()
            .' order by ordinal_position',
            [$this->getConnection()->getDatabaseName(), $this->getTable()]
        );

        return array_map(function ($value) {
            return $value->column_name;
        }, $columns);
        // return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    public function setPositions(array $positions)
    {
        $this->generatedPosition = $positions;
    }

    public function getPositions()
    {
        return $this->generatedPosition;
    }

    public function setAttributesCategoriesPotential(array $categories)
    {
        $this->attributesCategories = $categories;
    }

    public function getAttributeCategoriesPotential()
    {
        return $this->attributesCategories['potentialByCategory'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PlayerContract::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_retired', false);
    }

    public function scopeForClub(Builder $query, Club $club): Builder
    {
        return $query->where('players.instance_id', $club->instance_id)
            ->where('players.club_id', $club->id);
    }

    /** @param list<string> $fields */
    public function scopeWithTrainingProgress(Builder $query, array $fields): Builder
    {
        return $query->leftJoin('players_progress', 'players_progress.player_id', '=', 'players.id')
            ->select('players.*')
            ->addSelect(['players_progress.condition as training_condition', 'players_progress.last_progressed_at'])
            ->addSelect(array_map(fn (string $field): string => "players_progress.{$field} as progress_{$field}", $fields));
    }

    public function scopeWithInjuryStatusOn(Builder $query, string $date): Builder
    {
        return $query->addSelect(['is_injured' => PlayerInjury::query()->activeOn($date)
            ->whereColumn('player_injuries.player_id', 'players.id')
            ->whereColumn('player_injuries.instance_id', 'players.instance_id')
            ->selectRaw('1')->limit(1)]);
    }

    /**
     * Players without an injury covering the given date.
     */
    public function scopeNotInjuredOn(Builder $query, string $date): Builder
    {
        return $query->whereNotExists(PlayerInjury::query()->activeOn($date)
            ->whereColumn('player_injuries.player_id', 'players.id')
            ->whereColumn('player_injuries.instance_id', 'players.instance_id')
            ->selectRaw('1'));
    }

    public function scopeRetired(Builder $query): Builder
    {
        return $query->where('is_retired', true);
    }

    public function scopeKeyPlayers(Builder $query)
    {
        return $query->orderBy('potential', 'desc')->limit(3);
    }
}
