<?php

namespace Modules\Request\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Modules\Customer\Services\CustomerService;
use Modules\Stock\Models\Item;
use Modules\Stock\Services\ItemService;
use Modules\Stock\Services\StockMovementService;

/**
 * اندپوینت‌های JSON صفحهٔ «ثبت درخواست جدید»:
 *  - ساخت سریع مشتری (فقط نام و نام خانوادگی)
 *  - لیست صفحه‌بندی‌شدهٔ کالاهای دسته‌بندی‌های انتخاب‌شده (با موجودی انبار)
 *  - تغییر سریع قیمت خرید/فروش کالا
 */
class RequestBuilderController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        protected ItemService $itemService,
        protected StockMovementService $stockService,
    ) {}

    public function quickCustomer(HttpRequest $request): JsonResponse
    {
        // مطابق CustomerRequest: تنها فیلد اجباری مشتری «name» است
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'نام و نام خانوادگی مشتری الزامی است.',
        ]);

        $customer = $this->customerService->createCustomer(['name' => trim($data['name'])]);

        return response()->json(['id' => $customer->id, 'name' => $customer->name], 201);
    }

    public function categoryItems(HttpRequest $request): JsonResponse
    {
        $data = $request->validate([
            'category_ids'   => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $paginator = Item::query()
            ->with('category:id,name')
            ->whereIn('category_id', $data['category_ids'])
            ->orderBy('name')
            ->paginate((int) ($data['per_page'] ?? 5));

        $stockMap = $this->stockService->getCurrentStockMap($paginator->pluck('id')->all());

        $rows = $paginator->getCollection()->map(fn (Item $item) => [
            'id'             => $item->id,
            'name'           => $item->name,
            'category_id'    => $item->category_id,
            'category_name'  => $item->category?->name,
            'purchase_price' => (float) $item->purchase_price,
            'sale_price'     => (float) $item->sale_price,
            'tracks_stock'   => (bool) $item->tracks_stock,
            'stock'          => $item->tracks_stock ? (int) ($stockMap[$item->id] ?? 0) : null,
        ])->values();

        return response()->json([
            'data'         => $rows,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ]);
    }

    public function updateItemPrice(HttpRequest $request, int $item): JsonResponse
    {
        $data = $request->validate([
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price'     => ['required', 'numeric', 'min:0'],
        ], [
            'purchase_price.required' => 'قیمت خرید را وارد کنید.',
            'sale_price.required'     => 'قیمت فروش را وارد کنید.',
        ]);

        $updated = $this->itemService->updateItem($item, [
            'purchase_price' => $data['purchase_price'],
            'sale_price'     => $data['sale_price'],
        ]);

        return response()->json([
            'id'             => $updated->id,
            'purchase_price' => (float) $updated->purchase_price,
            'sale_price'     => (float) $updated->sale_price,
        ]);
    }
}
