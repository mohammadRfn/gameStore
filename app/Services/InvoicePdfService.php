<?php

namespace App\Services;

use App\Models\Invoice;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Output\Destination;

class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing('orderItems', 'customer', 'adjustments', 'serviceJobs.serviceTypes.serviceType');

        $defaultFontConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultFontConfig['fontDir'];

        $defaultFontVariables = (new FontVariables())->getDefaults();
        $fontData = $defaultFontVariables['fontdata'];

        $mpdf = new Mpdf([
            'mode'            => 'utf-8',
            'format'          => 'A4',
            'directionality'  => 'rtl',
            'default_font'    => 'vazirmatn',
            'margin_top'      => 12,
            'margin_bottom'   => 15,
            'margin_left'     => 12,
            'margin_right'    => 12,
            'fontDir'         => array_merge($fontDirs, [resource_path('fonts/Vazirmatn')]),
            'fontdata'        => $fontData + [
                'vazirmatn' => [
                    'R' => 'Vazirmatn-Regular.ttf',
                    'B' => 'Vazirmatn-Bold.ttf',
                    'L' => 'Vazirmatn-Light.ttf',
                    'M' => 'Vazirmatn-Medium.ttf',
                    'useOTL'          => 0xFF,
                    'useKashida'      => 75,
                ],
            ],
        ]);

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('invoice-' . $invoice->invoice_number . '.pdf', Destination::STRING_RETURN);
    }
}
