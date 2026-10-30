<?php

namespace App\Actions\Imports;

use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use ZipArchive;

/**
 * Streams the rows of an import file as lists of cell values (first row = headers), without loading it
 * into memory. xlsx/ods/csv/txt are read with OpenSpout; docx tables (or lines) are read from the XML;
 * old .xls files are converted to CSV with LibreOffice first.
 */
class ReadImportFileAction
{
    public const array EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt', 'ods', 'docx'];

    /**
     * @return Generator<int, list<mixed>>
     */
    public function handle(string $path, string $extension): Generator
    {
        $extension = strtolower($extension);

        if ($extension === 'docx') {
            yield from $this->docx($path);

            return;
        }

        if ($extension === 'xls') {
            $csv = $this->convertWithLibreOffice($path);
            try {
                yield from $this->spreadsheet($this->csvReader($csv), $csv);
            } finally {
                File::deleteDirectory(dirname($csv));
            }

            return;
        }

        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'ods' => new OdsReader,
            'csv', 'txt' => $this->csvReader($path),
            default => throw new RuntimeException("Files of type .{$extension} cannot be imported."),
        };

        yield from $this->spreadsheet($reader, $path);
    }

    /**
     * Rows of the first sheet that has any data.
     *
     * @return Generator<int, list<mixed>>
     */
    private function spreadsheet(XlsxReader|OdsReader|CsvReader $reader, string $path): Generator
    {
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $hadRows = false;
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var Row $row */
                    $hadRows = true;
                    yield array_values($row->toArray());
                }
                if ($hadRows) {
                    return;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * CSV/TXT with the delimiter and encoding guessed from the first 64 KB
     * (old Excel exports of Arabic data are often Windows-1256, not UTF-8).
     */
    private function csvReader(string $path): CsvReader
    {
        $sample = (string) file_get_contents($path, length: 65536);
        $firstLine = strtok(str_replace("\r", "\n", $sample), "\n") ?: '';

        $delimiter = ',';
        $best = 0;
        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $best) {
                [$delimiter, $best] = [$candidate, $count];
            }
        }

        $encoding = mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'CP1256';

        return new CsvReader(new CsvOptions(FIELD_DELIMITER: $delimiter, ENCODING: $encoding));
    }

    /**
     * Word files: table rows become rows; without tables each paragraph is one row under a "Text" header.
     *
     * @return Generator<int, list<mixed>>
     */
    private function docx(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Word file could not be opened.');
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $text = fn (string $fragment): string => trim(html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", ' ', ' '], $fragment)), ENT_QUOTES | ENT_XML1, 'UTF-8'));

        if (preg_match_all('#<w:tr[ >].*?</w:tr>#s', $xml, $tableRows) > 0) {
            foreach ($tableRows[0] as $tableRow) {
                preg_match_all('#<w:tc[ >].*?</w:tc>#s', $tableRow, $cells);
                yield array_map(fn (string $cell): string => (string) preg_replace('/\s+/u', ' ', $text($cell)), $cells[0]);
            }

            return;
        }

        yield ['Text'];
        foreach (preg_split('/\n+/', $text($xml)) ?: [] as $line) {
            if (trim($line) !== '') {
                yield [trim($line)];
            }
        }
    }

    /**
     * Convert an .xls file to CSV in a temporary directory; returns the CSV path.
     */
    private function convertWithLibreOffice(string $path): string
    {
        $directory = storage_path('app/private/imports/tmp/'.uniqid('xls-', true));
        File::ensureDirectoryExists($directory);

        $result = Process::timeout(300)->run([
            (string) config('services.libreoffice.binary'), '--headless', '--convert-to', 'csv:Text - txt - csv (StarCalc):44,34,76', '--outdir', $directory, $path,
        ]);

        $csv = File::glob($directory.'/*.csv')[0] ?? null;

        if (! $result->successful() || $csv === null) {
            File::deleteDirectory($directory);

            throw new RuntimeException('Reading old .xls files needs LibreOffice on the server. Install it, or save the file as .xlsx and upload again.');
        }

        return $csv;
    }
}
