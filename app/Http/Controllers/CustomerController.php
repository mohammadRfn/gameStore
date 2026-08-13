<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'name', 'email', 'request_status', 'invoice_status']);

        return Inertia::render('Customers/Index', [
            'customers' => $this->customerService->getAllCustomers($filters)->withQueryString(),
            'filters'   => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $this->customerService->createCustomer($data);

        return redirect()->route('customers.index')
            ->with('success', 'مشتری با موفقیت اضافه شد.');
    }

    public function show(int $id, Request $request): Response
    {
        $filters = $request->only(['request_status', 'invoice_status']);
        $customer = $this->customerService->getCustomerById($id, $filters);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function edit(int $id): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $this->customerService->getCustomerById($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $this->customerService->updateCustomer($id, $data);

        return redirect()->route('customers.show', $id)
            ->with('success', 'اطلاعات مشتری به‌روزرسانی شد.');
    }

    public function destroy(int $id)
    {
        $this->customerService->deleteCustomer($id);

        return redirect()->route('customers.index')
            ->with('success', 'مشتری حذف شد.');
    }
}
