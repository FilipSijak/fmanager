<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchPlayersRequest;
use App\Http\Resources\PlayerResource;
use App\Services\SearchService\SearchService;
use Illuminate\Http\JsonResponse;
use Throwable;

final class PlayerSearchController extends Controller
{
    public function __invoke(SearchPlayersRequest $request, SearchService $searchService): JsonResponse
    {
        try {
            $players = $searchService->searchPlayersByName(
                $request->string('q')->toString(),
                $request->integer('limit', 20),
                $request->integer('page', 1),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'Player search is temporarily unavailable.',
            ], 503);
        }

        $lastPage = min($players->lastPage(), intdiv(config('elasticsearch.max_results'), $players->perPage()));

        return response()->json([
            'data' => PlayerResource::collection($players->items())->resolve($request),
            'meta' => [
                'current_page' => $players->currentPage(),
                'per_page' => $players->perPage(),
                'total' => $players->total(),
                'last_page' => $lastPage,
                'has_more' => $players->currentPage() < $lastPage,
                'limited' => $players->lastPage() > $lastPage,
            ],
        ]);
    }
}
