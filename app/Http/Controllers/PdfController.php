<?php

namespace App\Http\Controllers;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
        public function generate(Request $request)
    {
        $html = $request->input('html');

        if (!$html) {
            return response()->json([
                'error' => 'HTML is required',
                'received' => $request->all()
            ], 400);
        }

        $pdf = Pdf::loadHTML($html);

        return response($pdf->stream('output.pdf'), 200)
            ->header('Content-Type', 'application/pdf');
    }
    
    public function merge(Request $request)
{
    $files = $request->file('pdfs');

    if (!$files || count($files) < 2) {
        return response()->json(['error' => 'Minimal dua file PDF dibutuhkan'], 400);
    }

    $pdf = new Fpdi();

    foreach ($files as $file) {
        $path = $file->getRealPath();
        $pageCount = $pdf->setSourceFile($path);
        for ($page = 1; $page <= $pageCount; $page++) {
            $tplIdx = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tplIdx);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplIdx);
        }
    }


    $outputPath = storage_path('app/public/merged.pdf');
    // $pdf->Output($outputPath, 'F');

    return response()->download($outputPath, 'merged.pdf')->deleteFileAfterSend();
}

}