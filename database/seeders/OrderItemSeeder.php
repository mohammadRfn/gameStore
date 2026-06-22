<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class OrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $invoice = Invoice::first(); 

        if (!$invoice) {
            $invoice = Invoice::create([
                'total_amount' => 100.50,
                'invoice_number' => 'INV-' . \Illuminate\Support\Str::random(10),
            ]);
        }

        $categoryGames = Category::where('name', 'بازی')->first();
        $categoryServices = Category::where('name', 'خدمات')->first();
        $categoryRepairs = Category::where('name', 'تعمیرات')->first();

        if (!$categoryGames) {
            $categoryGames = Category::create(['name' => 'بازی']);
        }

        if (!$categoryServices) {
            $categoryServices = Category::create(['name' => 'خدمات']);
        }

        if (!$categoryRepairs) {
            $categoryRepairs = Category::create(['name' => 'تعمیرات']);
        }

        $orderItems = [
            ['product_name' => 'Elden Ring', 'quantity' => 1, 'price' => 60.00, 'total_price' => 60.00, 'category_id' => $categoryGames->id],
            ['product_name' => 'Hogwarts Legacy', 'quantity' => 1, 'price' => 55.00, 'total_price' => 55.00, 'category_id' => $categoryGames->id],
            ['product_name' => 'Installation Service', 'quantity' => 1, 'price' => 20.00, 'total_price' => 20.00, 'category_id' => $categoryServices->id],
            ['product_name' => 'Console Repair', 'quantity' => 1, 'price' => 30.00, 'total_price' => 30.00, 'category_id' => $categoryRepairs->id],
        ];

        foreach ($orderItems as $item) {
            OrderItem::create([
                'invoice_id' => $invoice->id,  
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total_price' => $item['total_price'],
                'category_id' => $item['category_id'], 
            ]);
        }
    }
}
