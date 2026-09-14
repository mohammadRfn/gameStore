<?php

namespace Modules\DigitalMenu\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\DigitalMenu\Services\DigitalMenuService;

class DigitalMenuController extends Controller
{
    public function __construct(protected DigitalMenuService $digitalMenuService) {}

    public function activate(Request $request, int $invoiceId)
    {
        $data = $request->validate([
            'category_ids'   => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);

        $session = $this->digitalMenuService->activateForInvoice($invoiceId, $data['category_ids']);

        return response()->json([
            'code'       => $session->code,
            'status'     => $session->status,
            'expires_at' => $session->expires_at,
            'link'       => $this->digitalMenuService->buildLink($session->code),
        ]);
    }

    public function status(int $invoiceId)
    {
        $session = $this->digitalMenuService->statusForInvoice($invoiceId);

        return response()->json($session ? [
            'code'       => $session->code,
            'status'     => $session->status,
            'expires_at' => $session->expires_at,
            'link'       => $this->digitalMenuService->buildLink($session->code),
        ] : null);
    }
}