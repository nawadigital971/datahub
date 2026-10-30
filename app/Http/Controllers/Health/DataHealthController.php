<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ShowDataHealthRequest;
use App\Queries\Health\DataHealthQuery;
use Inertia\Inertia;
use Inertia\Response;

class DataHealthController extends Controller
{
    public function index(ShowDataHealthRequest $request, DataHealthQuery $health): Response
    {
        return Inertia::render('health/Index', [
            'health' => Inertia::defer(fn (): array => $health->handle()),
        ]);
    }
}
