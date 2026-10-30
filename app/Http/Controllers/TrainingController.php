<?php

namespace App\Http\Controllers;

use App\Services\TrainingService\TrainingManagementService;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(TrainingManagementService $training): Response
    {
        return Inertia::render('training/Training', $training->overview());
    }
}
