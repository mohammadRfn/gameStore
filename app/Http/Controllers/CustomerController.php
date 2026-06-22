<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function index(Request $request)
    {
        $filters   = $request->only(['name', 'email', 'request_status', 'invoice_status', 'search']);
        $customers = $this->customerService->getAllCustomers($filters);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => $filters,
        ]);
    }

    public function show(int $id, Request $request)
    {
        $filters  = $request->only(['request_status', 'invoice_status']);
        $customer = $this->customerService->getCustomerById($id, $filters);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(CustomerRequest $request)
    {
        $this->customerService->createCustomer($request->validated());
        return redirect()->route('customers.index');
    }

    public function edit(int $id)
    {
        $customer = $this->customerService->getCustomerById($id);
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(CustomerRequest $request, int $id)
    {
        $this->customerService->updateCustomer($id, $request->validated());
        return redirect()->route('customers.show', $id);
    }

    public function destroy(int $id)
    {
        $this->customerService->deleteCustomer($id);
        return redirect()->route('customers.index');
    }
}