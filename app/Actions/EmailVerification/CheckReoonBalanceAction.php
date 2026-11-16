<?php

namespace App\Actions\EmailVerification;

use App\Exceptions\EmailVerifierException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CheckReoonBalanceAction
{
    /**
     * Remaining credits on the email verifier account.
     *
     * @return array{daily: int, instant: int}
     *
     * @throws EmailVerifierException
     */
    public function handle(): array
    {
        try {
            $response = Http::baseUrl(config('services.reoon.url'))
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 1000, throw: false)
                ->get('check-account-balance/', ['key' => config('services.reoon.key')]);
        } catch (ConnectionException $exception) {
            throw new EmailVerifierException('Could not reach the email verifier: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful() || $response->json('status') !== 'success' || $response->json('api_status') !== 'active') {
            throw new EmailVerifierException('Email verifier balance check failed: '.($response->json('reason') ?? $response->body()));
        }

        return [
            'daily' => (int) $response->json('remaining_daily_credits', 0),
            'instant' => (int) $response->json('remaining_instant_credits', 0),
        ];
    }
}
