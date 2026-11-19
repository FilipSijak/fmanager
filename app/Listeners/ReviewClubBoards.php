<?php

namespace App\Listeners;

use App\Events\BoardReviewDue;
use App\Services\ClubService\BoardReviewService;

class ReviewClubBoards
{
    public function __construct(private readonly BoardReviewService $reviews) {}

    /**
     * Handle the event.
     */
    public function handle(BoardReviewDue $event): void
    {
        $this->reviews->process($event->instance->id, $event->seasonId, $event->reviewDate);
    }
}
