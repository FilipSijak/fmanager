<?php

namespace App\Services\SearchService;

use App\Models\Player;
use App\Support\GameContext;
use Illuminate\Database\Eloquent\Builder;

final class PlayerSearchQuery
{
    private const SEARCH_COLUMNS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick',
        'heading', 'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking',
        'tackling', 'technique', 'aggression', 'anticipation', 'bravery', 'composure',
        'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership',
        'of_the_ball', 'positioning', 'teamwork', 'workrate', 'acceleration', 'agility',
        'balance', 'jumping', 'natural_fitness', 'pace', 'stamina', 'strength',
        'technical', 'mental', 'physical',
    ];

    public function __construct(private readonly GameContext $gameContext) {}

    public function active(): Builder
    {
        return Player::query()
            ->forInstance($this->gameContext->instanceId())
            ->active();
    }

    public function activeAliased(): Builder
    {
        return Player::query()
            ->from('players AS p')
            ->select('p.*')
            ->where('p.instance_id', $this->gameContext->instanceId())
            ->where('p.is_retired', false);
    }

    public function activeMatchingAttributes(array $attributes): Builder
    {
        return $this->applyMinimumAttributes($this->active(), $attributes);
    }

    public function activeAliasedMatchingAttributes(array $attributes): Builder
    {
        return $this->applyMinimumAttributes($this->activeAliased(), $attributes, 'p.');
    }

    private function applyMinimumAttributes(
        Builder $query,
        array $attributes,
        string $columnPrefix = '',
    ): Builder {
        return $query->where(function (Builder $query) use ($attributes, $columnPrefix): void {
            foreach ($attributes as $attribute => $value) {
                if (! in_array($attribute, self::SEARCH_COLUMNS, true)) {
                    throw new \InvalidArgumentException("Unsupported player search attribute: {$attribute}");
                }

                $query->where($columnPrefix.$attribute, '>=', $value);
            }
        });
    }
}
