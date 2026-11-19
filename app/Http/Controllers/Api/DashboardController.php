<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryTransactionResource;
use App\Http\Resources\ProductResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** GET /api/dashboard/stats */
    public function stats(): JsonResponse
    {
        $stats = Product::active()->toBase()
            ->selectRaw('COUNT(*) as total_products')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END), 0) as low_stock')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END), 0) as out_of_stock')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as inventory_value')
            ->first();

        $movements = InventoryTransaction::toBase()
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN quantity ELSE 0 END), 0) as stock_in_units', [InventoryTransaction::TYPE_IN])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN quantity ELSE 0 END), 0) as stock_out_units', [InventoryTransaction::TYPE_OUT])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as today_transactions', [now()->startOfDay()])
            ->first();

        return response()->json([
            'stats' => [
                'total_products' => (int) $stats->total_products,
                'low_stock' => (int) $stats->low_stock,
                'out_of_stock' => (int) $stats->out_of_stock,
                'inventory_value' => (float) $stats->inventory_value,
                'total_transactions' => (int) $movements->total_transactions,
                'stock_in_units' => (int) $movements->stock_in_units,
                'stock_out_units' => (int) $movements->stock_out_units,
                'today_transactions' => (int) $movements->today_transactions,
            ],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /** GET /api/products/low-stock */
    public function lowStock(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 10), 1), 50);

        $products = Product::active()
            ->with('category')
            ->lowStock()
            ->orderBy('quantity')
            ->orderBy('id')
            ->take($limit)
            ->get();

        return ProductResource::collection($products)
            ->additional(['generated_at' => now()->toISOString()])
            ->response();
    }

    /** GET /api/transactions/recent */
    public function recentTransactions(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 8), 1), 50);

        $transactions = InventoryTransaction::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->latest()
            ->orderByDesc('id')
            ->take($limit)
            ->get();

        return InventoryTransactionResource::collection($transactions)
            ->additional(['generated_at' => now()->toISOString()])
            ->response();
    }
}
