<?php

namespace App\Models;

use App\Enums\CleaningRuleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One cleaning rule used when files are analysed (see CleaningRuleType for what key and value mean).
 *
 * @property int $id
 * @property CleaningRuleType $type
 * @property string $key
 * @property string|null $value
 * @property int $position
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'key', 'value', 'position', 'notes'])]
class CleaningRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CleaningRuleType::class,
        ];
    }
}
