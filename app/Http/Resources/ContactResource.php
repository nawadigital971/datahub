<?php

namespace App\Http\Resources;

use App\Enums\ContactType;
use App\Enums\EmailStatus;
use App\Enums\Permission;
use App\Models\Contact;
use App\Queries\Audience\AudienceLookups;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * One row of the Audience records table. Phones and emails are masked on the server unless the
 * user has contacts.view_unmasked, so unmasked values never reach the browser.
 *
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lookups = app(AudienceLookups::class);
        $unmasked = $request->user()?->can(Permission::ContactsViewUnmasked->value) ?? false;
        $sourceIds = $this->source_ids;

        return [
            'id' => $this->id,
            'is_business' => $this->type === ContactType::Business,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'company_name' => $this->company_name,
            'sector' => $lookups->label('sector', $this->sector_id),
            'source' => [
                'label' => $sourceIds === [] ? null : $lookups->label('source', (int) $sourceIds[0]),
                'more' => max(0, count($sourceIds) - 1),
                'year' => $this->last_seen_year,
            ],
            'location' => [
                'region' => $this->region_id ? $lookups->label('region', $this->region_id) : null,
                'area' => $this->area_id ? $lookups->label('area', $this->area_id) : null,
                'country' => $this->country_id ? $lookups->label('country', $this->country_id) : null,
            ],
            'mobile' => $unmasked ? $this->mobile_e164 : self::maskPhone($this->mobile_e164),
            'landline' => $unmasked ? $this->landline_e164 : self::maskPhone($this->landline_e164),
            'email' => $unmasked ? $this->email : self::maskEmail($this->email),
            'reach' => [
                'email' => $this->has_email,
                'whatsapp' => $this->has_mobile,
                'sms' => $this->has_uae_mobile,
            ],
            'flags' => $this->flags(),
        ];
    }

    /**
     * Status tags shown in the table ("Clean" when there is nothing to report).
     *
     * @return list<array{label: string, tone: 'flag'|'neutral'|'ok'}>
     */
    private function flags(): array
    {
        $flags = [];

        if ($this->do_not_contact) {
            $flags[] = ['label' => 'Do not contact', 'tone' => 'flag'];
        }
        if ($this->is_junk) {
            $flags[] = ['label' => 'Junk name', 'tone' => 'flag'];
        }
        if ($this->email_status === EmailStatus::Invalid) {
            $flags[] = ['label' => 'Bad email', 'tone' => 'flag'];
        }
        if ($this->name_en !== null && ! $this->name_en_verified) {
            $flags[] = ['label' => 'Check spelling', 'tone' => 'neutral'];
        }

        return $flags === [] ? [['label' => 'Clean', 'tone' => 'ok']] : $flags;
    }

    /**
     * "+971 50 ••• ••67" — country code, first two national digits and the last two.
     */
    public static function maskPhone(?string $e164): ?string
    {
        if ($e164 === null) {
            return null;
        }

        try {
            $number = PhoneNumberUtil::getInstance()->parse($e164);
            $countryCode = (string) $number->getCountryCode();
            $national = (string) $number->getNationalNumber();
        } catch (NumberParseException) {
            $countryCode = substr(ltrim($e164, '+'), 0, 3);
            $national = substr(ltrim($e164, '+'), 3);
        }

        return '+'.$countryCode.' '.substr($national, 0, 2).' ••• ••'.substr($national, -2);
    }

    /**
     * "ah•••@gmail.com" — first two characters of the local part and the domain.
     */
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'•••@'.$domain;
    }
}
