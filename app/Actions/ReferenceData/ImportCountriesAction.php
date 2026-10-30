<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\CountryData;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

class ImportCountriesAction
{
    /**
     * Add countries that are missing (by ISO code). Existing ones are never overwritten: they are
     * edited in the dashboard or by Claude over MCP.
     *
     * @param  list<CountryData>  $countries
     */
    public function handle(array $countries): int
    {
        DB::transaction(function () use ($countries): void {
            foreach ($countries as $country) {
                Country::query()->firstOrCreate(
                    ['iso2' => $country->iso2],
                    ['dial_code' => $country->dialCode, 'name' => $country->name, 'aliases' => $country->aliases],
                );
            }
        });

        return count($countries);
    }
}
