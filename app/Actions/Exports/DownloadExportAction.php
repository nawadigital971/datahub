<?php

namespace App\Actions\Exports;

use App\Models\Export;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadExportAction
{
    /**
     * Return the export file and record the first download.
     */
    public function handle(Export $export): Media
    {
        $file = $export->getFirstMedia('file')
            ?? throw new NotFoundHttpException('This export has no file (still running, failed or past its retention period).');

        if ($export->downloaded_at === null) {
            $export->update(['downloaded_at' => now()]);
        }

        return $file;
    }
}
