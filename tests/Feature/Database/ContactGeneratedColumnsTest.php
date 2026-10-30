<?php

use App\Enums\EmailStatus;
use App\Models\Contact;

it('builds a normalized search text from names, company, email and phone digits', function () {
    $contact = Contact::factory()->create([
        'name_en' => 'José Ali',
        'name_ar' => 'أَحْمَد إبراهيم فاطمة مصطفى',
        'company_name' => 'SoClean LLC',
        'email' => 'Ali@Example.com',
        'mobile_e164' => '+971501234567',
        'landline_e164' => '+97142345678',
    ]);

    expect($contact->fresh()->search_text)
        ->toBe('jose ali احمد ابراهيم فاطمه مصطفي soclean llc ali@example.com 971501234567 97142345678');
});

it('updates the search text when a name changes', function () {
    $contact = Contact::factory()->create(['name_en' => 'Old Name']);

    $contact->update(['name_en' => 'New Name']);

    expect($contact->fresh()->search_text)->toStartWith('new name');
});

it('derives the has_* channel flags from the stored values', function () {
    $contact = Contact::factory()->create([
        'mobile_e164' => '+966501234567',
        'landline_e164' => null,
        'email' => 'bad@example.com',
        'email_status' => EmailStatus::Invalid,
        'name_ar' => 'علي',
    ])->fresh();

    expect($contact->has_mobile)->toBeTrue()
        ->and($contact->has_uae_mobile)->toBeFalse()
        ->and($contact->has_email)->toBeFalse()
        ->and($contact->has_landline)->toBeFalse()
        ->and($contact->has_arabic_name)->toBeTrue();
});

it('marks UAE mobiles', function () {
    expect(Contact::factory()->create(['mobile_e164' => '+971551234567'])->fresh()->has_uae_mobile)->toBeTrue();
});
