<?php

namespace Tests\Unit\Search;

use App\Models\Person;
use App\Models\Player;
use App\Search\PlayerDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlayerDocumentTest extends TestCase
{
    #[Test]
    public function it_builds_a_searchable_player_document_and_explicit_mapping(): void
    {
        $person = new Person([
            'first_name' => 'Alpha',
            'last_name' => 'Striker',
        ]);
        $player = new Player;
        $player->forceFill([
            'id' => 10,
            'instance_id' => 2,
            'is_retired' => false,
            'position' => 'ST',
            'pace' => 18,
        ]);
        $player->setRelation('person', $person);

        $document = PlayerDocument::fromPlayer($player);
        $mapping = PlayerDocument::mapping();

        $this->assertSame(10, $document['id']);
        $this->assertSame(2, $document['instance_id']);
        $this->assertSame('Alpha Striker', $document['full_name']);
        $this->assertSame(18, $document['pace']);
        $this->assertSame('strict', $mapping['dynamic']);
        $this->assertSame('text', $mapping['properties']['full_name']['type']);
        $this->assertSame('integer', $mapping['properties']['pace']['type']);
    }
}
