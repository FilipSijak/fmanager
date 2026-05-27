<?php

namespace App\Services\NewsService;

enum NewsPriority: int
{
    case Urgent = 1;
    case High = 3;
    case Normal = 5;
    case Low = 10;
}
