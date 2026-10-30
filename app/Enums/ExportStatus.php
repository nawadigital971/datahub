<?php

namespace App\Enums;

enum ExportStatus: int
{
    case Queued = 1;
    case Running = 2;
    case Done = 3;
    case Failed = 4;

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Done => 'Done',
            self::Failed => 'Failed',
        };
    }
}
