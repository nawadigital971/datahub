<?php

namespace App\Http\Resources;

use App\Models\StagedRow;
use App\Queries\Audience\AudienceLookups;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the import preview: the original cells, the parsed (editable) values and the checks.
 *
 * @mixin StagedRow
 */
class StagedRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lookups = app(AudienceLookups::class);
        $option = fn (string $dimension, ?int $id): ?array => $id === null ? null : ['id' => $id, 'label' => $lookups->label($dimension, $id) ?? "#{$id}"];

        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'source_name' => $this->source_name,
            'type' => $this->type?->value,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'company_name' => $this->company_name,
            'mobile_e164' => $this->mobile_e164,
            'landline_e164' => $this->landline_e164,
            'email' => $this->email,
            'gender' => $this->gender?->value,
            'region' => $option('region', $this->region_id),
            'area' => $option('area', $this->area_id),
            'sector' => $option('sector', $this->sector_id),
            'nationality' => $option('country', $this->nationality_country_id),
            'address_raw' => $this->address_raw,
            'seen_year' => $this->seen_year,
            'call_outcome' => $this->call_outcome?->label(),
            'issues' => $this->issues,
            'severity' => $this->severity->key(),
            'match' => [
                'status' => $this->match_status->key(),
                'label' => $this->match_status->label(),
                'contact_id' => $this->matched_contact_id,
                'dup_of_row' => $this->whenLoaded('dupOf', fn () => $this->dupOf?->row_number),
            ],
            'action' => $this->action->key(),
            'action_locked' => $this->action_locked,
            'has_merge_choices' => $this->merge_choices !== null,
            'raw' => $this->raw,
            'extra' => $this->extra,
        ];
    }
}
