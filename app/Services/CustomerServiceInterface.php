<?php

namespace App\Services;

interface CustomerServiceInterface
{
    public function getAllCustomers( $filters = []);

    public function createCustomer(array $data);

    public function getCustomerById($id , $filters = []);

    public function updateCustomer($id, array $data);

    public function deleteCustomer($id);
}
