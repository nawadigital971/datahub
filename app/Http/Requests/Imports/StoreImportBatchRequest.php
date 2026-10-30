<?php

namespace App\Http\Requests\Imports;

use App\Actions\Imports\CheckBlockedFilenameAction;
use App\Actions\Imports\ReadImportFileAction;
use App\Enums\Permission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreImportBatchRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 102_400;

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ImportsCreate->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'extensions:'.implode(',', ReadImportFileAction::EXTENSIONS),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    $reason = app(CheckBlockedFilenameAction::class)->handle($value->getClientOriginalName());
                    if ($reason !== null) {
                        $fail("This file cannot be imported: {$reason}.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload an Excel (.xlsx, .xls), CSV, text, OpenDocument (.ods) or Word (.docx) file.',
            'file.max' => 'The file is larger than 100 MB. Split it into smaller files.',
        ];
    }
}
