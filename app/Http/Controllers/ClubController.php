<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ClubResource;
use App\Http\Resources\ClubSquadPlayerResource;
use App\Repositories\ClubRepository;

class ClubController extends Controller
{
    public function __construct(
        private readonly ClubRepository $clubRepository,
    ) {}

    public function show(int $clubId)
    {
        $club = $this->clubRepository->findOrFail($clubId);

        return ResponseHelper::success(
            (new ClubResource($club))->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }

    public function squad(int $clubId)
    {
        $players = $this->clubRepository->getSquadByPosition($clubId);

        return ResponseHelper::success(
            ClubSquadPlayerResource::collection($players)->resolve(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
