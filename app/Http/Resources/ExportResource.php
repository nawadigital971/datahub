<?php

namespace App\Http\Resources;

use App\DTOs\AudienceFilters;
use App\Enums\ExportStatus;
use App\Enums\Permission;
use App\Models\Export;
use App\Queries\Audience\AudienceFilterDescriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One export log entry (also used for progress polling).
 *
 * @mixin Export
 */
class ExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $file = $this->getFirstMedia('file');
        $user = $request->user();
        $canDownload = $user !== null
            && ($user->can(Permission::ExportsViewLog->value) || ($this->user_id === $user->id && $user->can(Permission::ExportsCreate->value)));

        return [
            'id' => $this->id,
            'user' => $this->user?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'channel' => ['key' => $this->channel->key(), 'label' => $this->channel->label()],
            'hashed' => $this->hashed,
            'audience' => app(AudienceFilterDescriber::class)->handle(AudienceFilters::fromArray($this->filters)),
            'row_count' => $this->row_count,
            'purpose' => $this->purpose,
            'status' => ['key' => strtolower($this->status->name), 'label' => $this->status->label()],
            'is_finished' => in_array($this->status, [ExportStatus::Done, ExportStatus::Failed], true),
            'downloaded_at' => $this->downloaded_at?->toIso8601String(),
            'file' => $file !== null ? ['name' => $file->file_name, 'size' => $file->size] : null,
            'download_url' => $file !== null && $canDownload ? route('exports.download', $this->id) : null,
        ];
    }
}
