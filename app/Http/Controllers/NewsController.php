<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\NewsService\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $newsService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return ResponseHelper::success(
            $this->newsService->getNews(! $request->boolean('all'))->toArray(),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }

    public function markAsRead(int $newsId): JsonResponse
    {
        return ResponseHelper::success(
            $this->newsService->markAsRead($newsId)->toArray(),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
