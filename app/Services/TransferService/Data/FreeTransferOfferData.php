<?php

namespace App\Services\TransferService\Data;

final readonly class FreeTransferOfferData
{
    public function __construct(
        public int $sourceClubId,
        public int $playerId,
        public int $salary,
        public int $transferFee = 0,
        public int $appearance = 0,
        public int $assist = 0,
        public int $goal = 0,
        public int $league = 0,
        public int $promotionSalaryRaise = 0,
        public int $demotionSalaryCut = 0,
        public int $cup = 0,
        public int $el = 0,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceClubId: (int) $data['source_club_id'],
            playerId: (int) $data['player_id'],
            salary: (int) $data['salary'],
            transferFee: (int) ($data['transfer_fee'] ?? 0),
            appearance: (int) ($data['appearance'] ?? 0),
            assist: (int) ($data['assist'] ?? 0),
            goal: (int) ($data['goal'] ?? 0),
            league: (int) ($data['league'] ?? 0),
            promotionSalaryRaise: (int) ($data['pc_promotion_salary_raise'] ?? 0),
            demotionSalaryCut: (int) ($data['pc_demotion_salary_cut'] ?? 0),
            cup: (int) ($data['cup'] ?? 0),
            el: (int) ($data['el'] ?? 0),
        );
    }
}
