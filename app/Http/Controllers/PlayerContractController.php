<?php

namespace App\Http\Controllers;

use App\Services\ContractService\ContractService;
use Inertia\Inertia;
use Inertia\Response;

class PlayerContractController extends Controller
{
    public function __construct(private readonly ContractService $contracts) {}

    public function index(): Response
    {
        return Inertia::render('contracts/Contracts', $this->contracts->contractOverview());
    }

    public function show(int $playerId): Response
    {
        $overview = $this->contracts->contractOverview($playerId);

        return Inertia::render('contracts/RenewContract', [
            'club' => $overview['club'], 'date' => $overview['date'], 'player' => $overview['players'][0],
        ]);
    }
}
