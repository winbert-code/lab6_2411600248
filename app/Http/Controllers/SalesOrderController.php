<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\ActivityLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $status = array_key_exists($status, SalesOrder::STATUSES) ? $status : '';

        $orders = SalesOrder::query()
            ->with(['items.product:id,name,sku', 'user:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('items.product', fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->when($from !== '', fn ($query) => $query->whereDate('order_date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('order_date', '<=', $to))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales-orders.index', [
            'orders' => $orders,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'quantity', 'unit_price']),
            'statuses' => SalesOrder::STATUSES,
            'filters' => compact('search', 'status', 'from', 'to'),
        ]);
    }

    public function create(): View
    {
        return view('sales-orders.form', [
            'order' => new SalesOrder([
                'order_number' => SalesOrder::generateNumber(),
                'order_date' => now()->toDateString(),
            ]),
            'items' => collect([['product_id' => '', 'quantity' => 1, 'unit_price' => '']]),
            'products' => Product::orderBy('name')->get(),
            'action' => route('sales-orders.store'),
            'method' => 'POST',
            'editing' => false,
        ]);
    }

    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data) {
            $order = SalesOrder::create([
                'order_number' => ($data['order_number'] ?? null) ?: SalesOrder::generateNumber(),
                'customer' => $data['customer'],
                'order_date' => $data['order_date'],
                'due_date' => ($data['due_date'] ?? null) ?: null,
                'status' => SalesOrder::STATUS_PENDING,
                'notes' => ($data['notes'] ?? null) ?: null,
                'user_id' => auth()->id(),
            ]);

            $this->saveItems($order, $data['items']);

            return $order;
        });

        ActivityLog::record(
            ActivityLog::MODULE_SALES_ORDER,
            ActivityLog::ACTION_CREATED,
            "Sales order {$order->order_number} was created for {$order->customer} with {$order->items->count()} item(s).",
            $order,
        );

        return redirect()->route('sales-orders.show', $order)
            ->with('success', 'Sales order created. Complete it when the items are shipped.');
    }

    public function show(SalesOrder $salesOrder): View
    {
        $salesOrder->load(['items.product.brand', 'items.product.category', 'user']);

        return view('sales-orders.show', ['order' => $salesOrder]);
    }

    public function edit(SalesOrder $salesOrder): View
    {
        abort_unless($salesOrder->isPending(), 403, 'Only pending sales orders can be edited.');

        return view('sales-orders.form', [
            'order' => $salesOrder,
            'items' => $salesOrder->items,
            'products' => Product::orderBy('name')->get(),
            'action' => route('sales-orders.update', $salesOrder),
            'method' => 'PUT',
            'editing' => true,
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->isPending(), 403, 'Only pending sales orders can be edited.');
        $data = $request->validated();

        DB::transaction(function () use ($data, $salesOrder) {
            $salesOrder->update([
                'order_number' => ($data['order_number'] ?? null) ?: $salesOrder->order_number,
                'customer' => $data['customer'],
                'order_date' => $data['order_date'],
                'due_date' => ($data['due_date'] ?? null) ?: null,
                'notes' => ($data['notes'] ?? null) ?: null,
            ]);

            $this->saveItems($salesOrder, $data['items']);
        });

        ActivityLog::record(
            ActivityLog::MODULE_SALES_ORDER,
            ActivityLog::ACTION_UPDATED,
            "Sales order {$salesOrder->order_number} was updated.",
            $salesOrder,
        );

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', 'Sales order updated.');
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->isPending(), 403, 'Only pending sales orders can be deleted.');
        $number = $salesOrder->order_number;

        DB::transaction(function () use ($salesOrder) {
            $salesOrder->items()->delete();
            $salesOrder->delete();
        });

        ActivityLog::record(
            ActivityLog::MODULE_SALES_ORDER,
            ActivityLog::ACTION_CANCELLED,
            "Pending sales order {$number} was deleted.",
        );

        return redirect()->route('sales-orders.index')
            ->with('success', 'Pending sales order deleted.');
    }

    /** Completing an order turns every item into a stock-out movement. */
    public function complete(SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->isPending(), 403, 'This sales order has already been processed.');
        abort_if($salesOrder->items()->doesntExist(), 422, 'A sales order needs at least one item before it can be completed.');

        // Aggregate the requested quantity per product so duplicated lines are
        // checked correctly before any stock movement is written.
        $required = $salesOrder->items
            ->groupBy('product_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        foreach ($required as $productId => $quantity) {
            $product = Product::query()->findOrFail($productId);

            if ((int) $product->quantity < $quantity) {
                return back()->with('error', "Insufficient stock for {$product->name}. Only {$product->quantity} unit(s) are available.");
            }
        }

        try {
            DB::transaction(function () use ($salesOrder) {
                foreach ($salesOrder->items as $item) {
                    InventoryTransaction::create([
                        'product_id' => $item->product_id,
                        'type' => InventoryTransaction::TYPE_OUT,
                        'quantity' => $item->quantity,
                        'reference' => $salesOrder->order_number,
                        'reason' => 'Sales order completed',
                        'notes' => "Sold to {$salesOrder->customer}",
                        'user_id' => auth()->id(),
                    ]);
                }

                $salesOrder->update(['status' => SalesOrder::STATUS_COMPLETED]);
            });
        } catch (InsufficientStockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        ActivityLog::record(
            ActivityLog::MODULE_SALES_ORDER,
            ActivityLog::ACTION_COMPLETED,
            "Sales order {$salesOrder->order_number} was completed. {$salesOrder->totalQuantity()} unit(s) were removed from stock.",
            $salesOrder,
        );

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', 'Sales order completed and stock levels updated.');
    }

    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->isPending(), 403, 'Only pending sales orders can be cancelled.');

        $salesOrder->update(['status' => SalesOrder::STATUS_CANCELLED]);

        ActivityLog::record(
            ActivityLog::MODULE_SALES_ORDER,
            ActivityLog::ACTION_CANCELLED,
            "Sales order {$salesOrder->order_number} was cancelled.",
            $salesOrder,
        );

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', 'Sales order cancelled.');
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function saveItems(SalesOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }
    }
}
