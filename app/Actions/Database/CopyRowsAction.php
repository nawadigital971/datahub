<?php

namespace App\Actions\Database;

use Illuminate\Support\Facades\DB;
use Pdo\Pgsql;
use RuntimeException;

/**
 * Bulk-inserts rows with PostgreSQL COPY (much faster than INSERT for thousands of rows).
 * Values must already be in COPY text form: arrays as '{…}', json as strings, booleans as 't'/'f'.
 */
class CopyRowsAction
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<int|string|bool|null>>  $rows
     */
    public function handle(string $table, array $columns, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $pdo = DB::connection()->getPdo();
        if (! $pdo instanceof Pgsql) {
            throw new RuntimeException('COPY needs a PostgreSQL connection.');
        }

        $lines = array_map(fn (array $row): string => implode("\t", array_map($this->value(...), $row)), $rows);

        // null_as is placed inside an E'' string by PDO, so the backslash is escaped once more.
        if (! $pdo->copyFromArray($table, $lines, "\t", '\\\\N', implode(',', $columns))) {
            throw new RuntimeException("COPY into {$table} failed.");
        }

        return count($rows);
    }

    private function value(int|string|bool|null $value): string
    {
        return match (true) {
            $value === null => '\N',
            is_bool($value) => $value ? 't' : 'f',
            default => str_replace(['\\', "\t", "\n", "\r"], ['\\\\', '\t', '\n', '\r'], (string) $value),
        };
    }
}
