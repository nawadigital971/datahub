<?php

namespace App\Queries\Audience;

use App\Models\Segment;
use App\Models\User;

/**
 * The current user's saved segments plus segments shared by others.
 *
 * @phpstan-type SavedSegment array{id: int, name: string, subtitle: string, filters: array<string, string>, can_delete: bool}
 */
class SavedSegmentsQuery
{
    public const int LIMIT = 30;

    /**
     * @return list<SavedSegment>
     */
    public function handle(User $user): array
    {
        return array_values(Segment::query()
            ->with('user:id,name')
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('is_shared', true))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Segment $segment): array => [
                'id' => $segment->id,
                'name' => $segment->name,
                'subtitle' => $segment->user_id === $user->id
                    ? ($segment->is_shared ? 'Saved by you · shared' : 'Saved by you')
                    : 'Shared by '.($segment->user->name ?? 'a former user'),
                'filters' => array_map(strval(...), $segment->filters),
                'can_delete' => $segment->user_id === $user->id,
            ])
            ->all());
    }
}
