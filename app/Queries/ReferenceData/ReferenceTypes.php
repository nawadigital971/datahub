<?php

namespace App\Queries\ReferenceData;

use App\Queries\ReferenceData\Types\AreaType;
use App\Queries\ReferenceData\Types\CleaningRulesType;
use App\Queries\ReferenceData\Types\CountryType;
use App\Queries\ReferenceData\Types\DoNotContactType;
use App\Queries\ReferenceData\Types\MappingTemplateType;
use App\Queries\ReferenceData\Types\ReferenceType;
use App\Queries\ReferenceData\Types\RegionType;
use App\Queries\ReferenceData\Types\SectorType;
use App\Queries\ReferenceData\Types\SourceGroupType;
use Illuminate\Database\Eloquent\Model;

/**
 * Every editable reference list, by key. Used by the Reference data page and the MCP tools.
 */
class ReferenceTypes
{
    /** @var list<class-string<ReferenceType<covariant Model>>> */
    public const array TYPES = [
        RegionType::class,
        AreaType::class,
        CountryType::class,
        SectorType::class,
        SourceGroupType::class,
        CleaningRulesType::class,
        MappingTemplateType::class,
        DoNotContactType::class,
    ];

    /**
     * @return array<string, ReferenceType<Model>>
     */
    public function all(): array
    {
        $types = [];
        foreach (self::TYPES as $class) {
            /** @var ReferenceType<Model> $type each type only ever receives its own models */
            $type = app($class);
            $types[$type->key()] = $type;
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return ReferenceType<Model>|null
     */
    public function find(string $key): ?ReferenceType
    {
        return $this->all()[$key] ?? null;
    }
}
