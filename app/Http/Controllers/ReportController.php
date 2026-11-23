<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $movements = InventoryTransaction::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->betweenDates($from !== '' ? $from : null, $to !== '' ? $to : null)
            ->latest()
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $lowStock = Product::active()->with('category')->lowStock()->orderBy('quantity')->get();

        $valuation = Product::active()->toBase()
            ->selectRaw('COUNT(*) as products')
            ->selectRaw('SUM(quantity) as units')
            ->selectRaw('SUM(quantity * unit_price) as total')
            ->first();

        return view('reports.index', [
            'movements' => $movements,
            'lowStock' => $lowStock,
            'valuation' => $valuation,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function products(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if (! in_array($status, [Product::STATUS_ACTIVE, Product::STATUS_INACTIVE], true)) {
            $status = '';
        }

        $query = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id');

        $products = (clone $query)
            ->with(['transactions' => function ($query) use ($from, $to) {
                $query->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
                    ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to));
            }])
            ->paginate(15)
            ->withQueryString();

        $products->each(function (Product $product): void {
            $transactions = $product->transactions;

            $product->movement_count = $transactions->count();
            $product->units_in = (int) $transactions->where('type', InventoryTransaction::TYPE_IN)->sum('quantity');
            $product->units_out = (int) $transactions->where('type', InventoryTransaction::TYPE_OUT)->sum('quantity');
        });

        $summary = (clone $query)->get(['quantity', 'unit_price']);

        return view('reports.products', [
            'products' => $products,
            'productCount' => $summary->count(),
            'unitsOnHand' => (int) $summary->sum('quantity'),
            'stockValue' => $summary->sum(fn (Product $product) => $product->stockValue()),
            'search' => $search,
            'status' => $status,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function orders(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $salesStatus = $request->string('sales_status')->toString();
        $purchaseStatus = $request->string('purchase_status')->toString();

        if (! array_key_exists($salesStatus, SalesOrder::STATUSES)) {
            $salesStatus = '';
        }

        if (! array_key_exists($purchaseStatus, PurchaseOrder::STATUSES)) {
            $purchaseStatus = '';
        }

        $salesQuery = SalesOrder::query()
            ->when($from !== '', fn ($query) => $query->whereDate('order_date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('order_date', '<=', $to))
            ->when($salesStatus !== '', fn ($query) => $query->where('status', $salesStatus))
            ->latest('order_date')
            ->orderByDesc('id');

        $purchaseQuery = PurchaseOrder::query()
            ->when($from !== '', fn ($query) => $query->whereDate('order_date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('order_date', '<=', $to))
            ->when($purchaseStatus !== '', fn ($query) => $query->where('status', $purchaseStatus))
            ->latest('order_date')
            ->orderByDesc('id');

        $salesOrders = (clone $salesQuery)
            ->with(['items.product:id,name,sku', 'user:id,name'])
            ->paginate(10, ['*'], 'sales_page')
            ->withQueryString();

        $purchaseOrders = (clone $purchaseQuery)
            ->with(['items.product:id,name,sku', 'user:id,name'])
            ->paginate(10, ['*'], 'purchase_page')
            ->withQueryString();

        $salesSummary = (clone $salesQuery)->with('items')->get();
        $purchaseSummary = (clone $purchaseQuery)->with('items')->get();

        return view('reports.orders', [
            'salesOrders' => $salesOrders,
            'purchaseOrders' => $purchaseOrders,
            'salesStatuses' => SalesOrder::STATUSES,
            'purchaseStatuses' => PurchaseOrder::STATUSES,
            'salesCount' => $salesSummary->count(),
            'salesTotal' => $salesSummary->sum(fn (SalesOrder $order) => $order->totalAmount()),
            'purchaseCount' => $purchaseSummary->count(),
            'purchaseTotal' => $purchaseSummary->sum(fn (PurchaseOrder $order) => $order->totalAmount()),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'sales_status' => $salesStatus,
                'purchase_status' => $purchaseStatus,
            ],
        ]);
    }

    public function daily(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $movements = InventoryTransaction::query()
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->get(['type', 'quantity', 'created_at']);

        $sales = SalesOrder::query()
            ->completed()
            ->with('items')
            ->when($from !== '', fn ($query) => $query->whereDate('updated_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('updated_at', '<=', $to))
            ->get(['id', 'updated_at']);

        $purchases = PurchaseOrder::query()
            ->received()
            ->with('items')
            ->when($from !== '', fn ($query) => $query->whereDate('updated_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('updated_at', '<=', $to))
            ->get(['id', 'updated_at']);

        $byDay = collect();

        $add = function (string $day, string $key, float|int $amount) use ($byDay): void {
            $row = $byDay->get($day, [
                'date' => $day,
                'movements' => 0,
                'units_in' => 0,
                'units_out' => 0,
                'sales_orders' => 0,
                'sales_total' => 0.0,
                'purchase_orders' => 0,
                'purchase_total' => 0.0,
            ]);

            $row[$key] += $amount;
            $byDay->put($day, $row);
        };

        foreach ($movements as $movement) {
            $day = $movement->created_at?->toDateString();

            if ($day === null) {
                continue;
            }

            $add($day, 'movements', 1);

            if ($movement->type === InventoryTransaction::TYPE_IN) {
                $add($day, 'units_in', (int) $movement->quantity);
            }

            if ($movement->type === InventoryTransaction::TYPE_OUT) {
                $add($day, 'units_out', (int) $movement->quantity);
            }
        }

        foreach ($sales as $order) {
            $day = $order->updated_at?->toDateString();

            if ($day === null) {
                continue;
            }

            $add($day, 'sales_orders', 1);
            $add($day, 'sales_total', $order->totalAmount());
        }

        foreach ($purchases as $order) {
            $day = $order->updated_at?->toDateString();

            if ($day === null) {
                continue;
            }

            $add($day, 'purchase_orders', 1);
            $add($day, 'purchase_total', $order->totalAmount());
        }

        return view('reports.daily', [
            'days' => $byDay->values()->sortByDesc('date')->values(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $transactions = InventoryTransaction::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->betweenDates($from !== '' ? $from : null, $to !== '' ? $to : null)
            ->orderBy('created_at')
            ->get();

        $callback = function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Type', 'Product', 'SKU', 'Quantity', 'Previous', 'Balance', 'Reference', 'Reason', 'User', 'Notes']);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->created_at?->format('Y-m-d H:i:s'),
                    $transaction->type_label,
                    $transaction->product?->name,
                    $transaction->product?->sku,
                    $transaction->signedQuantity(),
                    $transaction->previous_quantity,
                    $transaction->balance_after,
                    $transaction->reference,
                    $transaction->reason,
                    $transaction->user?->name ?? 'System',
                    $transaction->notes,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stock-movement-report.csv"',
        ]);
    }
}
