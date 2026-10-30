<?php

namespace App\Http\Requests\Exports;

use App\Enums\Permission;
use App\Models\Export;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reading or downloading an export: its creator (who may still create exports) or anyone who can see the export log.
 */
class ShowExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $export = $this->route('export');
        $user = $this->user();

        if (! $export instanceof Export || $user === null) {
            return false;
        }

        return $user->can(Permission::ExportsViewLog->value)
            || ($export->user_id === $user->id && $user->can(Permission::ExportsCreate->value));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
