<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ShowDataHealthRequest;
use App\Queries\Health\DataHealthQuery;
use App\Queries\Health\EmailVerificationStatusQuery;
use Inertia\Inertia;
use Inertia\Response;

class DataHealthController extends Controller
{
    public function index(ShowDataHealthRequest $request, DataHealthQuery $health, EmailVerificationStatusQuery $emailVerification): Response
    {
        return Inertia::render('health/Index', [
            'health' => Inertia::defer(fn (): array => $health->handle()),
            'emailVerification' => $request->canVerifyEmails()
                ? Inertia::defer(fn (): array => $emailVerification->handle(), 'email-verification')
                : null,
        ]);
    }
}
