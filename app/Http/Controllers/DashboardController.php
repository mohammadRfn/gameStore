<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Request as ServiceRequest;
use App\Models\ServiceJob;
use Inertia\Inertia;
use Inertia\Response;

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
