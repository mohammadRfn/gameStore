<?php

namespace Modules\Service\Services;

use Modules\Profile\Models\StoreProfile;
use Modules\Service\Models\ServiceJob;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * پی‌دی‌اف گزارش سرویس — همان قالب و فونت پی‌دی‌اف فاکتور (InvoicePdfService)
 */
class ServiceJobPdfService
{
    public function generate(ServiceJob $job): string
    {
        $job->load([
            'customer',
            'request',
            'invoice',
            'serviceTypes.serviceType',
            'items' => fn ($q) => $q->with(['item' => fn ($i) => $i->withTrashed()]),
        ]);

        $storeProfile = StoreProfile::primary()->first();

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
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ],
            ],
        ]);

        $html = view('pdf.service', ['job' => $job, 'storeProfile' => $storeProfile])->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('service-' . $job->id . '.pdf', Destination::STRING_RETURN);
    }
}
