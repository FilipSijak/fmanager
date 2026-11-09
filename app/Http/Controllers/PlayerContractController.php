<?php

namespace App\Http\Controllers;

use App\Services\PersonService\PersonService;
use Inertia\Inertia;
use Inertia\Response;

class PlayerContractController extends Controller
{
    public function __construct(private readonly PersonService $persons) {}

    public function index(): Response
    {
        return Inertia::render('contracts/Contracts', $this->persons->contractOverview());
    }

    public function show(int $playerId): Response
    {
        $overview = $this->persons->contractOverview($playerId);

        return Inertia::render('contracts/RenewContract', [
            'club' => $overview['club'], 'date' => $overview['date'], 'player' => $overview['players'][0],
        ]);
    }
}
