<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\ShowContactRequest;
use App\Http\Resources\ContactDetailsResource;
use App\Models\Contact;
use App\Queries\Contacts\ContactDetailsQuery;

class ContactController extends Controller
{
    /**
     * Everything about one contact for the details panel (JSON).
     */
    public function show(ShowContactRequest $request, Contact $contact, ContactDetailsQuery $details): ContactDetailsResource
    {
        return new ContactDetailsResource($details->handle($contact));
    }
}
