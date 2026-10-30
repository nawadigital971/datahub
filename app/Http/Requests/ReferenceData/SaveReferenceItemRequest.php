<?php

namespace App\Http\Requests\ReferenceData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class SaveReferenceItemRequest extends FormRequest
{
    use ResolvesReferenceType;

    /**
     * The type's own rules, under "values.".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->referenceType();
        if ($type === null) {
            return [];
        }

        $item = $this->route('item');
        $existing = is_numeric($item) ? $type->find((int) $item) : null;
        $values = $this->input('values');

        $rules = ['values' => ['required', 'array']];
        foreach ($type->rules($existing instanceof Model ? $existing : null, is_array($values) ? $values : []) as $field => $fieldRules) {
            $rules["values.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = [];
        foreach ($this->referenceType()?->fields() ?? [] as $field) {
            $names["values.{$field['name']}"] = mb_strtolower($field['label']);
        }

        return $names;
    }
}
