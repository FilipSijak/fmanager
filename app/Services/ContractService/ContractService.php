<?php

namespace App\Services\ContractService;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\ContractRepository;
use App\Repositories\PlayerRepository;
use App\Services\ContractService\Domain\PlayerContractRenewal;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsTypes\PlayerContractNews;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractService
{
    public function __construct(
        private readonly ContractRepository $contractRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly GameContext $gameContext,
        private readonly PlayerContractRenewal $contractRenewal,
        private readonly NewsService $news,
        private readonly PlayerContractNews $contractNews,
        private readonly PlayerSearchIndexDispatcher $playerSearch,
    ) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function renewPlayerContract(int $playerId, array $data): array
    {
        return DB::transaction(function () use ($playerId, $data): array {
            $instance = $this->contractRepository->managedInstance($this->gameContext->instanceId(), true);
            abort_if($instance->club_id === null, 403);
            $date = (string) $instance->instance_date;
            $club = $instance->club;
            $player = $this->contractRepository->playerForRenewal($club, $playerId, $date, true);
            if ($data['expected_contract_end'] !== $player->contract->contract_end) {
                throw ValidationException::withMessages(['expected_contract_end' => 'The contract changed. Reload this screen before making another offer.']);
            }
            $months = (int) $data['months'];
            $endsAt = $this->contractRenewal->endsAt($date, $months, $player->contract->contract_end);
            $terms = $this->contractRenewal->terms($data);
            $account = $this->contractRepository->contractAccount($club);
            if ($terms['signing_fee'] > min((int) $account->balance, (int) $account->transfer_budget)) {
                throw ValidationException::withMessages(['transfer_fee' => 'The signing fee exceeds the available cash or transfer budget.']);
            }
            if ($this->contractRepository->annualContractWages($club, $player->id, $date) + $terms['salary'] * 52 > (int) $account->salaries_yearly_budget) {
                throw ValidationException::withMessages(['salary' => 'The salary exceeds the annual wage budget.']);
            }
            $decision = $this->contractRenewal->consider((int) $player->ambition, (int) $club->rank, $player->contract, $terms);
            if ($decision['status'] !== 'accepted') {
                $this->contractRepository->recordRenewalOffer($player, $terms, $date, $endsAt, $months, $decision['status']);

                $this->news->publish($this->contractNews->decision($player, $instance, $decision['message']));

                return $decision;
            }
            $this->contractRepository->applyContractRenewal($player, $account, $terms, $date, $endsAt, $months);

            $this->news->publish($this->contractNews->decision($player, $instance, 'Contract renewed until '.$endsAt.'.'));

            return ['status' => 'accepted', 'message' => 'Contract renewed until '.$endsAt.'.', 'ends_at' => $endsAt];
        });
    }

    public function processPlayerContracts(Instance $instance): void
    {
        DB::transaction(function () use ($instance): void {
            $instance = $this->contractRepository->managedInstance((int) $instance->id, true);
            $date = (string) $instance->instance_date;
            $reminderDate = CarbonImmutable::parse($date)->addMonthsNoOverflow(6)->toDateString();
            foreach ($this->contractRepository->contractsEndingBy((int) $instance->id, $reminderDate) as $player) {
                if ($player->contract->contract_end < $date) {
                    if ($player->club_id === $instance->club_id) {
                        $this->news->publish($this->contractNews->expired($player, $instance));
                    }
                    $contract = $player->contract;
                    $this->playerRepository->releasePlayer($player);
                    $this->contractRepository->deletePlayerContract($contract);
                    $this->playerSearch->synchronizePlayer((int) $player->id);

                    continue;
                }
                if ($player->club_id === $instance->club_id) {
                    if ($player->contract->renewal_reminded_for !== $player->contract->contract_end) {
                        $this->news->publish($this->contractNews->reminder($player, $instance));
                        $this->contractRepository->markContractReminder($player);
                    }

                    continue;
                }
                $this->renewAiPlayerContract($player, $date);
            }
        });
    }

    private function renewAiPlayerContract(Player $player, string $date): void
    {
        $club = $player->club;
        if ($club === null || $club->account === null) {
            return;
        }
        $terms = $this->contractRenewal->terms($player->contract->getAttributes());
        $terms['signing_fee'] = 0;
        $decision = $this->contractRenewal->consider((int) $player->ambition, (int) $club->rank, $player->contract, $terms);
        if ($decision['status'] === 'rejected') {
            return;
        }
        $terms['salary'] = $decision['counter_salary'] ?? $terms['salary'];
        $account = $this->contractRepository->contractAccount($club);
        if ($this->contractRepository->annualContractWages($club, $player->id, $date) + $terms['salary'] * 52 > (int) $account->salaries_yearly_budget) {
            return;
        }
        $endsAt = $this->contractRenewal->endsAt($date, 36, $player->contract->contract_end);
        $this->contractRepository->applyContractRenewal($player, $account, $terms, $date, $endsAt, 36);
    }

    /** @return array<string, mixed> */
    public function contractOverview(?int $playerId = null): array
    {
        $instance = $this->contractRepository->managedInstance($this->gameContext->instanceId());
        abort_if($instance->club_id === null, 403);
        $date = $this->gameContext->instanceDate();
        $club = $instance->club;
        $players = $playerId === null
            ? $this->contractRepository->clubContracts($club, $date)
            : collect([$this->contractRepository->playerForRenewal($club, $playerId, $date)]);
        $contracts = $players->map(function (Player $player) use ($date): array {
            $contract = $player->contract;
            $endsAt = CarbonImmutable::parse($contract->contract_end);

            return [
                'id' => $player->id, 'name' => trim($player->person->first_name.' '.$player->person->last_name),
                'position' => $player->position, 'contract_id' => $contract->id,
                'starts_at' => $contract->contract_start, 'ends_at' => $contract->contract_end,
                'expiring' => $endsAt->lte(CarbonImmutable::parse($date)->addMonthsNoOverflow(6)),
                'terms' => array_merge(['transfer_fee' => 0], collect(['salary', 'appearance', 'goal', 'assist', 'clean_sheet', 'league', 'promotion', 'cup', 'el', 'cl'])
                    ->mapWithKeys(fn (string $field): array => [$field => (int) $contract->{$field}])->all()),
            ];
        })->sortBy('ends_at')->values()->all();

        return ['club' => $club->only(['id', 'name']), 'date' => $date, 'players' => $contracts];
    }
}
