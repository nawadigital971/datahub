<?php

namespace App\Enums;

enum ImportOrigin: int
{
    case Upload = 1;
    case Mcp = 2;

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Upload',
            self::Mcp => 'MCP',
        };
    }
}
