<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Mcp\IssueMcpTokenAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\RevokeMcpTokenAction;
use App\Actions\Users\SaveRoleAction;
use App\Actions\Users\SetUserActiveAction;
use App\Actions\Users\UpdateUserAction;
use App\DTOs\UserData;
use App\Enums\Permission;
use App\Enums\Role as RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserStatusRequest;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\StoreMcpTokenRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Queries\Users\UserListQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, UserListQuery $users): Response
    {
        $page = $users->handle($request);

        return Inertia::render('admin/users/Index', [
            'users' => [
                'data' => array_map(fn (User $user): array => UserListQuery::present($user, $request->user()), $page->items()),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'links' => ['prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            ],
            'roles' => Role::query()->with('permissions:id,name')->orderBy('id')->get()->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'locked' => $role->name === RoleName::Admin->value,
                'users' => User::role($role->name)->count(),
            ])->all(),
            'permissions' => Permission::values(),
            'filters' => ['search' => $request->input('filter.search')],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $user = $action->handle(UserData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$user->name}. They were emailed a link to set their password."])->back();
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $action->handle($user, UserData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Saved {$user->name}."])->back();
    }

    public function deactivate(ChangeUserStatusRequest $request, User $user, SetUserActiveAction $action): RedirectResponse
    {
        $action->handle($user, false, $request->user());

        return Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} can no longer sign in. Their history is kept."])->back();
    }

    public function reactivate(ChangeUserStatusRequest $request, User $user, SetUserActiveAction $action): RedirectResponse
    {
        $action->handle($user, true, $request->user());

        return Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} can sign in again."])->back();
    }

    public function issueToken(StoreMcpTokenRequest $request, User $user, IssueMcpTokenAction $action): RedirectResponse
    {
        $token = $action->handle($user, $request->string('name')->toString(), $request->integer('days'));

        return Inertia::flash(['mcp_token' => ['user' => $user->name, 'token' => $token->plainTextToken, 'url' => url('/mcp')]])->back();
    }

    public function revokeToken(ChangeUserStatusRequest $request, User $user, int $token, RevokeMcpTokenAction $action): RedirectResponse
    {
        $action->handle($user, $token);

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Token revoked.'])->back();
    }

    public function storeRole(StoreRoleRequest $request, SaveRoleAction $action): RedirectResponse
    {
        /** @var list<string> $permissions */
        $permissions = $request->validated('permissions');
        $role = $action->handle($permissions, name: $request->string('name')->toString());

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Added the {$role->name} role."])->back();
    }

    public function updateRole(UpdateRoleRequest $request, Role $role, SaveRoleAction $action): RedirectResponse
    {
        /** @var list<string> $permissions */
        $permissions = $request->validated('permissions');
        $action->handle($permissions, $role);

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Saved the {$role->name} role."])->back();
    }
}
