<?php

namespace App\Services\TransferService\Data;

use App\Services\TransferService\TransferType;

final readonly class TransferOfferData
{
    public function __construct(
        public int $sourceClubId,
        public int $targetClubId,
        public int $playerId,
        public TransferType $transferType,
        public int $amount,
        public int $installments,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceClubId: (int) $data['source_club_id'],
            targetClubId: (int) $data['target_club_id'],
            playerId: (int) $data['player_id'],
            transferType: TransferType::from((int) $data['transfer_type']),
            amount: (int) $data['amount'],
            installments: (int) ($data['installments'] ?? 0),
        );
    }
}
