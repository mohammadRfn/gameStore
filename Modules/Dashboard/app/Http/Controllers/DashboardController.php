<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Customer\Models\Customer;
use Modules\Invoice\Models\Invoice;
use Modules\Request\Models\Request as ServiceRequest;
use Modules\Service\Models\ServiceJob;
use Modules\Stock\Models\Item;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'customers_count'     => Customer::count(),
                'open_requests'       => ServiceRequest::whereIn('status', ['pending', 'in_progress'])->count(),
                'items_count'         => Item::count(),
                'active_service_jobs' => ServiceJob::whereNotIn('status', ['completed', 'delivered'])->count(),
            ],
            'recentRequests' => ServiceRequest::with('categories')
                ->latest()
                ->take(5)
                ->get(['id', 'customer_name', 'description', 'status']),
            'recentInvoices' => Invoice::latest()
                ->take(5)
                ->get(['id', 'invoice_number', 'total_amount', 'is_confirmed']),
        ]);
    }
}
