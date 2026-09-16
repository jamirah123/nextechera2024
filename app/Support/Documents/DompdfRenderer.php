<?php

namespace App\Support\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class DompdfRenderer
{
    public function renderView(string $view, array $data = [], string $paper = 'A4'): string
    {
        return $this->renderHtml(View::make($view, $data)->render(), $paper);
    }

    public function renderHtml(string $html, string $paper = 'A4'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
