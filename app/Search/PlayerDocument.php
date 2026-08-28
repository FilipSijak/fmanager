<?php

namespace App\Search;

use App\Models\Player;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;

final class PlayerDocument
{
    /** @return array<string, mixed> */
    public static function fromPlayer(Player $player): array
    {
        $attributes = collect(self::attributeFields())
            ->mapWithKeys(fn (string $field): array => [$field => (int) $player->{$field}])
            ->all();

        return array_merge([
            'id' => (int) $player->id,
            'instance_id' => (int) $player->instance_id,
            'is_retired' => (bool) $player->is_retired,
            'first_name' => (string) $player->person->first_name,
            'last_name' => (string) $player->person->last_name,
            'full_name' => trim($player->person->first_name.' '.$player->person->last_name),
            'position' => (string) $player->position,
        ], $attributes);
    }

    /** @return array<string, mixed> */
    public static function mapping(): array
    {
        $properties = [
            'id' => ['type' => 'integer'],
            'instance_id' => ['type' => 'integer'],
            'is_retired' => ['type' => 'boolean'],
            'first_name' => self::nameFieldMapping(),
            'last_name' => self::nameFieldMapping(),
            'full_name' => self::nameFieldMapping(),
            'position' => ['type' => 'keyword'],
        ];

        foreach (self::attributeFields() as $field) {
            $properties[$field] = ['type' => 'integer'];
        }

        return [
            'dynamic' => 'strict',
            'properties' => $properties,
        ];
    }

    /** @return list<string> */
    private static function attributeFields(): array
    {
        return array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS,
        );
    }

    /** @return array<string, mixed> */
    private static function nameFieldMapping(): array
    {
        return [
            'type' => 'text',
            'fields' => [
                'keyword' => ['type' => 'keyword'],
            ],
        ];
    }
}
