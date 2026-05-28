<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\NewsService\NewsService;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $newsService,
    ) {
    }

    public function index(): JsonResponse
    {
        return ResponseHelper::success(
            $this->newsService->getNews()->toArray(),
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
