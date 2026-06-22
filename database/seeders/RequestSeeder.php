<?php

namespace Database\Seeders;

use App\Models\Category;  // اضافه کردن مدل Category
use App\Models\Request;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // پیدا کردن اولین دسته‌بندی از دیتابیس
        $category = Category::first(); 

        // اگر هیچ دسته‌بندی (category) در دیتابیس وجود نداشت، یک دسته‌بندی جدید ایجاد می‌کنیم
        if (!$category) {
            $category = Category::create([
                'name' => 'Default Category',  // برای مثال یک نام پیش‌فرض برای دسته‌بندی
            ]);
        }

        // ایجاد رکوردهای درخواست (requests)
        Request::create([
            'customer_name' => 'John Doe',  // نام مشتری فرضی
            'category_id' => $category->id,  // استفاده از ID دسته‌بندی
            'description' => 'This is a sample request for testing purposes.', // توضیحات نمونه
            'status' => 'pending',  // وضعیت درخواست فرضی
        ]);

        // اگر نیاز به اضافه کردن رکوردهای بیشتر دارید، می‌توانید همینطور ادامه دهید
        // Request::create([...]);  
    }
}
