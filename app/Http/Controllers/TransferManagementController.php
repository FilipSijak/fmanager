<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\RespondTransferRequest;
use App\Http\Requests\StorePlayerOfferRequest;
use App\Http\Resources\TransferResource;
use App\Services\TransferService\TransferManagementService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransferManagementController extends Controller
{
    public function __construct(private readonly TransferManagementService $transfers) {}

    public function index(): Response
    {
        $instance = $this->transfers->managedInstance();

        return Inertia::render('transfers/Transfers', [
            'club' => $instance->club->only(['id', 'name']),
            'transferBudget' => (int) ($instance->club->account?->transfer_budget ?? 0),
            'activeTransfers' => TransferResource::collection($this->transfers->forManagedClub()->whereNotIn('transfer_status', TransferManagementService::TERMINAL_STATUSES)->get())->resolve(),
            'history' => $this->transfers->forManagedClub()->whereIn('transfer_status', TransferManagementService::TERMINAL_STATUSES)
                ->paginate(20)->through(fn ($transfer): array => (new TransferResource($transfer))->resolve()),
        ]);
    }

    public function offer(int $playerId): Response
    {
        $player = $this->transfers->offerPlayer($playerId);
        $instance = $this->transfers->managedInstance();

        return Inertia::render('transfers/PlayerOffer', [
            'player' => [
                'id' => $player->id, 'name' => trim($player->person->first_name.' '.$player->person->last_name),
                'position' => $player->position, 'value' => (int) $player->value,
                'club' => $player->club?->only(['id', 'name']), 'salary' => (int) ($player->contract?->salary ?? 0),
            ],
            'club' => $instance->club->only(['id', 'name']),
            'transferBudget' => (int) ($instance->club->account?->transfer_budget ?? 0),
        ]);
    }

    public function store(StorePlayerOfferRequest $request): JsonResponse
    {
        $transfer = $this->transfers->offer($request->validated());

        return ResponseHelper::success(['id' => $transfer->id], 201);
    }

    public function respond(RespondTransferRequest $request, int $transferId): JsonResponse
    {
        $transfer = $this->transfers->respond($transferId, $request->validated());

        return ResponseHelper::success(['id' => $transfer->id]);
    }
}
