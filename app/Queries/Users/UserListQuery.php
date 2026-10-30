<?php

namespace App\Queries\Users;

use App\Actions\Mcp\IssueMcpTokenAction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserListQuery
{
    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(User::query()->with(['roles:id,name', 'tokens' => fn ($query) => $query->whereJsonContains('abilities', IssueMcpTokenAction::ABILITY)->latest()]), $request)
            ->allowedFilters(AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(
                fn (Builder $where) => $where->whereLike('name', '%'.$value.'%')->orWhereLike('email', '%'.$value.'%'),
            )))
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->allowedIncludes()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(User $user, User $viewer): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->isActive(),
            'is_self' => $user->is($viewer),
            'created_at' => $user->created_at?->toIso8601String(),
            'tokens' => $user->tokens->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
