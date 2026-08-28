<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SearchPlayersRequest;
use App\Http\Resources\PlayerResource;
use App\Services\SearchService\SearchService;
use Throwable;

final class PlayerSearchController extends Controller
{
    public function __invoke(SearchPlayersRequest $request, SearchService $searchService)
    {
        try {
            $players = $searchService->searchPlayersByName(
                $request->string('q')->toString(),
                $request->integer('limit', 20),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'Player search is temporarily unavailable.',
            ], 503);
        }

        return ResponseHelper::success(
            PlayerResource::collection($players)->resolve($request),
            ResponseHelper::RESPONSE_SUCCESS_CODE,
        );
    }
}
