<?php

namespace App\Actions\EmailVerification;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CheckReoonBalanceAction
{
    /**
     * Remaining daily credits on the email verifier account (instant credits are never spent).
     *
     * @throws RequestException|RuntimeException
     */
    public function handle(): int
    {
        $response = Http::baseUrl(config('services.reoon.url'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 1000)
            ->get('check-account-balance/', ['key' => config('services.reoon.key')])
            ->throw();

        if ($response->json('status') !== 'success' || $response->json('api_status') !== 'active') {
            throw new RuntimeException('Email verifier balance check failed: '.($response->json('reason') ?? $response->body()));
        }

        return (int) $response->json('remaining_daily_credits', 0);
    }
}
