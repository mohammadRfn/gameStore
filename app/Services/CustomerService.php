<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function getAllCustomers(array $filters = []): LengthAwarePaginator
    {
        $query = Customer::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        if (isset($filters['request_status'])) {
            $query->whereHas('requests', function (Builder $q) use ($filters) {
                $q->where('status', $filters['request_status']);
            });
        }

        if (isset($filters['invoice_status'])) {
            $query->whereHas('invoices', function (Builder $q) use ($filters) {
                $q->where('is_confirmed', $filters['invoice_status']);
            });
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhereHas('requests', function (Builder $q) use ($filters) {
                        $q->where('status', 'like', '%' . $filters['search'] . '%');
                    })
                    ->orWhereHas('invoices', function (Builder $q) use ($filters) {
                        $q->where('invoice_number', 'like', '%' . $filters['search'] . '%');
                    });
            });
        }

        return $query->paginate(10);
    }

    public function createCustomer(array $data): Customer
    {
        return Customer::create($data);
    }

    public function getCustomerById(int $id, array $filters = []): Customer
    {
        $customer = Customer::with(['requests.categories', 'invoices'])->findOrFail($id);

        if (isset($filters['request_status'])) {
            $customer->requests = $customer->requests->filter(
                fn($r) => $r->status === $filters['request_status']
            )->values();
        }

        if (isset($filters['invoice_status'])) {
            $customer->invoices = $customer->invoices->filter(
                fn($i) => $i->is_confirmed === $filters['invoice_status']
            )->values();
        }

        return $customer;
    }

    public function updateCustomer(int $id, array $data): Customer
    {
        $customer = Customer::findOrFail($id);
        $customer->update($data);
        return $customer;
    }

    public function deleteCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
    }
}
