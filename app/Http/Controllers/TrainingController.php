<?php

namespace App\Http\Controllers;

use App\Services\TrainingService\TrainingService;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(TrainingService $training): Response
    {
        return Inertia::render('training/Training', $training->overview());
    }
}
