<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * The email verifier (Reoon) refused or failed a request. In the dashboard it becomes an error toast;
 * in scheduled commands it is reported like any other failure.
 */
class EmailVerifierException extends RuntimeException
{
    /**
     * Send the user back with the verifier's reason (JSON requests use the default rendering).
     */
    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        return Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()])->back();
    }
}
