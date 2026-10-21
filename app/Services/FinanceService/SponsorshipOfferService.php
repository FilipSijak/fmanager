<?php

namespace App\Services\FinanceService;

use App\GameEntityType;
use App\Models\Club;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipOffer;
use App\Services\FinanceService\Domain\SponsorshipCalculator;
use App\Services\FinanceService\Domain\SponsorshipOfferCalculator;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsType;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SponsorshipOfferService
{
    public const int MAX_APPROACHES_PER_SEASON = 3;

    public function __construct(
        private readonly SponsorshipOfferCalculator $calculator,
        private readonly NewsService $news,
        private readonly GameContext $context,
    ) {}

    /** @return Collection<int, SponsorshipOffer> */
    public function pendingForManagedClub(): Collection
    {
        $instance = Instance::query()->findOrFail($this->context->instanceId());

        return SponsorshipOffer::query()->forInstance($instance->id)
            ->where('club_id', $instance->club_id)
            ->where('status', SponsorshipOfferStatus::Pending)
            ->whereDate('respond_by', '>=', $this->context->instanceDate())
            ->with('sponsorAccount.gameEntity')
            ->get();
    }

    public function process(Instance $instance): void
    {
        $date = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $sponsors = GameEntityAccount::query()->where('instance_id', $instance->id)
            ->whereHas('gameEntity', fn (Builder $query) => $query->where('instance_id', $instance->id)->where('type', GameEntityType::SPONSOR->value))
            ->with('gameEntity')->get();
        if ($sponsors->isEmpty()) {
            return;
        }

        $season = Season::query()->where('instance_id', $instance->id)->findOrFail($instance->season_id);
        Club::query()->forInstance($instance->id)
            ->whereDoesntHave('sponsorshipContracts', fn (Builder $query) => $query
                ->where('instance_id', $instance->id)
                ->whereDate('expires_at', '>', $date->addMonthsNoOverflow(6)->endOfMonth()->toDateString()))
            ->orderBy('id')
            ->each(function (Club $club) use ($instance, $season, $date, $sponsors): void {
                DB::transaction(function () use ($instance, $season, $date, $sponsors, $club): void {
                    $lockedClub = Club::query()->forInstance($instance->id)->lockForUpdate()->findOrFail($club->id);
                    SponsorshipOffer::query()->forInstance($instance->id)->where('club_id', $club->id)
                        ->where('status', SponsorshipOfferStatus::Pending)->whereDate('respond_by', '<', $date->toDateString())
                        ->update(['status' => SponsorshipOfferStatus::Expired->value]);

                    $pending = SponsorshipOffer::query()->where('club_id', $club->id)->where('status', SponsorshipOfferStatus::Pending)->first();
                    if ($pending !== null) {
                        if ($club->id !== $instance->club_id) {
                            $this->acceptLocked($pending, $date);
                        }

                        return;
                    }

                    $contract = SponsorshipContract::query()->forInstance($instance->id)->where('club_id', $club->id)->orderByDesc('expires_at')->first();
                    if ($contract !== null && $date->lt($contract->expires_at->subMonthsNoOverflow(6))) {
                        return;
                    }
                    if (SponsorshipOffer::query()->where('club_id', $club->id)->whereDate('offered_on', '>=', $date->toDateString())->exists()) {
                        return;
                    }
                    $attempts = SponsorshipOffer::query()->forInstance($instance->id)->where('club_id', $club->id)
                        ->where('season_id', $season->id)->selectRaw('sponsor_account_id, COUNT(*) AS attempts')
                        ->groupBy('sponsor_account_id')->pluck('attempts', 'sponsor_account_id');
                    $occupied = SponsorshipContract::query()->forInstance($instance->id)
                        ->whereDate('expires_at', '>=', $date->toDateString())
                        ->pluck('sponsor_account_id')->flip();
                    $sponsor = $sponsors->filter(fn (GameEntityAccount $account): bool => $this->calculator->isEligible((int) $lockedClub->rank, $account->gameEntity->rank)
                        && (int) $attempts->get($account->id, 0) < self::MAX_APPROACHES_PER_SEASON)
                        ->sortBy(fn (GameEntityAccount $account): array => [
                            $occupied->has($account->id) ? 1 : 0,
                            abs($account->gameEntity->rank - (int) $lockedClub->rank),
                            $account->id,
                        ])->first();
                    if ($sponsor === null) {
                        return;
                    }

                    $term = random_int(1, 5);
                    [$startsAt, $expiresAt] = $this->contractDates($season, $date, $contract, $term);
                    $offer = SponsorshipOffer::query()->create([
                        'instance_id' => $instance->id, 'club_id' => $club->id, 'season_id' => $season->id,
                        'sponsor_account_id' => $sponsor->id, 'status' => SponsorshipOfferStatus::Pending,
                        'attempt_number' => (int) $attempts->get($sponsor->id, 0) + 1,
                        'term_seasons' => $term,
                        'annual_amount' => $this->calculator->initialAmount((int) $lockedClub->rank, $sponsor->gameEntity->rank),
                        'maximum_annual_amount' => $this->calculator->maximumAmount((int) $lockedClub->rank, $sponsor->gameEntity->rank),
                        'offered_on' => $date, 'starts_at' => $startsAt, 'expires_at' => $expiresAt,
                        'respond_by' => $startsAt->subDay(),
                    ]);
                    if ($club->id === $instance->club_id) {
                        $this->publish($offer, $date, 'Sponsorship offer received', $sponsor->gameEntity->name.' has offered an annual sponsorship of £'.number_format($offer->annual_amount).'. Review the offer in Finance.');
                    } else {
                        $this->acceptLocked($offer, $date);
                    }
                });
            });
    }

    public function respond(int $offerId, string $action, ?int $annualAmount = null): SponsorshipOffer
    {
        $instance = Instance::query()->findOrFail($this->context->instanceId());
        $date = CarbonImmutable::parse($this->context->instanceDate())->startOfDay();

        return DB::transaction(function () use ($offerId, $instance, $date, $action, $annualAmount): SponsorshipOffer {
            Club::query()->forInstance($instance->id)->lockForUpdate()->findOrFail($instance->club_id);
            $offer = SponsorshipOffer::query()->forInstance($instance->id)->where('club_id', $instance->club_id)
                ->lockForUpdate()->findOrFail($offerId);
            if ($action === 'accept' && $offer->status === SponsorshipOfferStatus::Accepted) {
                return $offer->load('sponsorAccount.gameEntity');
            }
            if ($offer->status !== SponsorshipOfferStatus::Pending) {
                throw new DomainException('This sponsorship offer is no longer active.');
            }
            if ($date->gt($offer->respond_by)) {
                throw new DomainException('This sponsorship offer has expired.');
            }

            if ($action === 'accept') {
                $this->acceptLocked($offer, $date);
                $this->publish($offer, $date, 'Sponsorship agreement signed', 'Your club has agreed a sponsorship starting '.$offer->starts_at->toDateString().'.');
            } elseif ($action === 'reject') {
                $offer->update(['status' => SponsorshipOfferStatus::Rejected]);
            } elseif ($action === 'negotiate') {
                if ($annualAmount === null || $annualAmount <= $offer->annual_amount || $annualAmount > SponsorshipCalculator::MAX_ANNUAL_AMOUNT) {
                    throw new DomainException('Your counter-offer must exceed the current offer and be no more than £30 million.');
                }
                $agreed = $annualAmount <= $offer->maximum_annual_amount;
                $offer->update([
                    'annual_amount' => $agreed ? $annualAmount : $this->calculator->counterAmount($offer->annual_amount, $offer->maximum_annual_amount),
                    'negotiation_rounds' => $offer->negotiation_rounds + 1,
                    'negotiation_response' => $agreed ? 'agreed' : 'counteroffer',
                ]);
            } else {
                throw new DomainException('Unknown sponsorship offer action.');
            }

            return $offer->fresh()->load('sponsorAccount.gameEntity');
        });
    }

    private function acceptLocked(SponsorshipOffer $offer, CarbonImmutable $date): void
    {
        if ($offer->status !== SponsorshipOfferStatus::Pending || $date->gt($offer->respond_by)) {
            throw new DomainException('This sponsorship offer is no longer available.');
        }
        if (SponsorshipContract::query()->forInstance($offer->instance_id)->where('club_id', $offer->club_id)
            ->whereDate('starts_at', '<=', $offer->expires_at->toDateString())
            ->whereDate('expires_at', '>=', $offer->starts_at->toDateString())->exists()) {
            throw new DomainException('Your club already has a sponsorship agreement covering these seasons.');
        }
        $contract = SponsorshipContract::query()->create([
            'instance_id' => $offer->instance_id, 'club_id' => $offer->club_id,
            'sponsor_account_id' => $offer->sponsor_account_id, 'annual_amount' => $offer->annual_amount,
            'starts_at' => $offer->starts_at, 'expires_at' => $offer->expires_at,
        ]);
        $offer->update(['status' => SponsorshipOfferStatus::Accepted, 'accepted_contract_id' => $contract->id]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function contractDates(Season $season, CarbonImmutable $date, ?SponsorshipContract $contract, int $term): array
    {
        $anchor = CarbonImmutable::parse($season->start_date);
        $startsAt = $anchor;
        $after = $contract !== null && $contract->expires_at->gt($date) ? $contract->expires_at : $date;
        while ($startsAt->lte($after)) {
            $startsAt = $startsAt->addYearNoOverflow();
        }

        return [$startsAt, CarbonImmutable::parse($season->end_date)->addYearsNoOverflow($startsAt->year - $anchor->year + $term - 1)];
    }

    private function publish(SponsorshipOffer $offer, CarbonImmutable $date, string $title, string $content): void
    {
        $this->news->publish(new NewsItem(
            instanceId: $offer->instance_id, seasonId: $offer->season_id, clubId: $offer->club_id,
            competitionId: null, title: $title, content: $content,
            type: NewsType::Sponsorship, publishedAt: $date,
        ));
    }
}
