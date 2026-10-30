<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use App\Queries\ReferenceData\ReferenceTypes;
use App\Queries\ReferenceData\Types\DoNotContactType;
use App\Queries\ReferenceData\Types\ReferenceType;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Reference lists over MCP: everyone who imports may read them (the do-not-contact list only with
 * contacts.update); changing one needs that list's own permission.
 */
trait UsesReferenceTypes
{
    /**
     * @return array<string, ReferenceType<Model>>
     */
    protected function readableTypes(mixed $user): array
    {
        return array_filter(
            app(ReferenceTypes::class)->all(),
            fn (ReferenceType $type): bool => ! $type instanceof DoNotContactType || $this->canEdit($user, $type),
        );
    }

    /**
     * @param  ReferenceType<Model>  $type
     */
    protected function canEdit(mixed $user, ReferenceType $type): bool
    {
        return $user instanceof User && $user->can($type->permission()->value);
    }

    /**
     * The list named in "type" when the user may change it, else an error response.
     *
     * @return ReferenceType<Model>|Response
     */
    protected function editableType(Request $request): ReferenceType|Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $key = $request->get('type');
        $type = is_string($key) ? app(ReferenceTypes::class)->find($key) : null;
        if ($type === null) {
            return Response::error('Unknown list "'.(is_scalar($key) ? $key : '').'". Lists: '.implode(', ', app(ReferenceTypes::class)->keys()).'.');
        }
        if (! $this->canEdit($request->user(), $type)) {
            return Response::error("Your account cannot change {$type->label()} (needs {$type->permission()->value}).");
        }

        return $type;
    }

    protected function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}
