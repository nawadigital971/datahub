<?php

namespace App\Models;

use App\Enums\MappingLayout;
use Database\Factories\MappingTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How the columns of a known spreadsheet layout map onto staged-row fields.
 *
 * @property int $id
 * @property string $name
 * @property MappingLayout $layout
 * @property array<string, string> $column_map
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'layout', 'column_map'])]
class MappingTemplate extends Model
{
    /** @use HasFactory<MappingTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => MappingLayout::class,
            'column_map' => 'array',
        ];
    }
}
