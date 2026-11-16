<?php

namespace App\Http\Controllers\Health;

use App\Actions\EmailVerification\SubmitEmailVerificationTaskAction;
use App\DTOs\EmailVerificationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\StoreEmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailVerificationController extends Controller
{
    public function store(StoreEmailVerificationRequest $request, SubmitEmailVerificationTaskAction $action): RedirectResponse
    {
        $task = $action->handle(EmailVerificationData::fromRequest($request));

        return Inertia::flash('toast', $task === null
            ? ['type' => 'success', 'message' => 'Every contact email is already checked or being checked.']
            : ['type' => 'success', 'message' => sprintf('Sent %s emails to the verifier. Results arrive within about 10 minutes of it finishing.', number_format(count($task->emails)))]
        )->back();
    }
}
