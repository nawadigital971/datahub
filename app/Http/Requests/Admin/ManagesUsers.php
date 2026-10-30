<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;

trait ManagesUsers
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersManage->value) ?? false;
    }
}
