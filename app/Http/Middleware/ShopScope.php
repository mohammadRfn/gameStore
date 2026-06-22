<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Shop;

class ShopScope
{
    public function handle(Request $request, Closure $next)
    {
        $shopId = $request->input('shop_id') ?? $request->header('X-Shop-ID');
        
        if (!$shopId) {
            throw ValidationException::withMessages([
                'shop_id' => 'Shop ID is required for all API requests.'
            ]);
        }

        $shop = Shop::find($shopId);
        if (!$shop || !$shop->is_active) {
            throw ValidationException::withMessages([
                'shop_id' => 'Invalid or inactive shop.'
            ]);
        }

        app()->instance('current_shop', $shop);
        $request->merge(['shop_id' => $shopId]);

        //return $next($request);
        return response()->json(['error' => 'Shop scope disabled']);
    }
}
