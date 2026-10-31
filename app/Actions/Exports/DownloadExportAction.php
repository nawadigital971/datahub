<?php

namespace App\Actions\Exports;

use App\Models\Export;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadExportAction
{
    /**
     * Stream the export file and record the first download.
     *
     * The file is checked before any header is sent, so a missing file gives a clear 404
     * instead of a broken download.
     */
    public function handle(Export $export): StreamedResponse
    {
        $file = $export->getFirstMedia('file')
            ?? throw new NotFoundHttpException('This export has no file (still running, failed or past its retention period).');

        $disk = Storage::disk($file->disk);
        $path = $file->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            Log::error('Export file is missing on disk.', ['export_id' => $export->id, 'disk' => $file->disk, 'path' => $disk->path($path)]);

            throw new NotFoundHttpException('The export file is missing on the server. Please run the export again.');
        }

        if ($export->downloaded_at === null) {
            $export->update(['downloaded_at' => now()]);
        }

        return $disk->download($path, $file->file_name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
