<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Response;

trait ServesPdfDownload
{
    protected function pdfDownload(string $binary, string $filename): Response
    {
        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
