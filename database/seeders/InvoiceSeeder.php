<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Request;  // اضافه کردن مدل Request برای ارتباط با جدول requests
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $request = Request::first(); 

        if (!$request) {
            $request = Request::create([
                'name' => 'Initial Request',  
            ]);
        }

        Invoice::create([
            'request_id' => $request->id,  
            'total_amount' => 100.50,      
            'invoice_number' => 'INV-' . Str::random(10),  
        ]);

       
    }
}
