<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Role as RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    use ManagesUsers {
        authorize as canManageUsers;
    }

    /**
     * The Admin role always has every permission and cannot be edited.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->canManageUsers() && $role instanceof Role && $role->name !== RoleName::Admin->value;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', Permission::values())],
        ];
    }
}
