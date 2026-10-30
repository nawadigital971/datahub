<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportTarget;
use App\Enums\Permission;
use App\Models\ImportBatch;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmMappingRequest extends FormRequest
{
    /** Batches that can still be (re)mapped. */
    public const array MAPPABLE = [ImportBatchStatus::Mapping, ImportBatchStatus::Ready, ImportBatchStatus::Failed];

    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof ImportBatch
            && in_array($batch->status, self::MAPPABLE, true)
            && ($this->user()?->can(Permission::ImportsCreate->value) ?? false);
    }

    /**
     * Every column goes to a known target, and at least one gives a way to reach people.
     *
     * @param  list<string>  $headers
     */
    public static function columnMapRule(array $headers): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($headers): void {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $header => $target) {
                if (! in_array($header, $headers, true) || ! is_string($target) || ! ImportTarget::isValid($target)) {
                    $fail('Choose a valid destination for every column.');

                    return;
                }
            }
            if (array_intersect($value, ImportTarget::contactTargets()) === []) {
                $fail('Map at least one column to Phone, Landline, Email or Notes, otherwise nobody can be contacted.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['save_as_template.unique' => 'A template with this name already exists. Choose another name.'];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ImportBatch $batch */
        $batch = $this->route('batch');
        $headers = $batch->headers ?? [];

        return [
            'mapping_template_id' => ['nullable', 'integer', 'exists:mapping_templates,id'],
            'save_as_template' => [
                'nullable',
                'string',
                'max:100',
                ...($this->user()?->can(Permission::SettingsUpdate->value) ? [] : [Rule::unique('mapping_templates', 'name')]),
            ],
            'column_map' => ['required', 'array', self::columnMapRule($headers)],
        ];
    }
}
