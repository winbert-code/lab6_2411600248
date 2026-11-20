<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\ActivityLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Support\NotifiesOrderActions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    use NotifiesOrderActions;

    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $status = array_key_exists($status, PurchaseOrder::STATUSES) ? $status : '';

        $orders = PurchaseOrder::query()
            ->with(['items.product:id,name,sku', 'user:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
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

        return view('purchase-orders.index', [
            'orders' => $orders,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'unit_price']),
            'statuses' => PurchaseOrder::STATUSES,
            'filters' => compact('search', 'status', 'from', 'to'),
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.form', [
            'order' => new PurchaseOrder([
                'order_number' => PurchaseOrder::generateNumber(),
                'order_date' => now()->toDateString(),
            ]),
            'items' => collect([['product_id' => '', 'quantity' => 1, 'unit_cost' => '']]),
            'products' => Product::orderBy('name')->get(),
            'action' => route('purchase-orders.store'),
            'method' => 'POST',
            'editing' => false,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create([
                'order_number' => ($data['order_number'] ?? null) ?: PurchaseOrder::generateNumber(),
                'supplier' => $data['supplier'],
                'order_date' => $data['order_date'],
                'expected_date' => ($data['expected_date'] ?? null) ?: null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => ($data['notes'] ?? null) ?: null,
                'user_id' => auth()->id(),
            ]);

            $this->saveItems($order, $data['items']);

            return $order;
        });

        ActivityLog::record(
            ActivityLog::MODULE_PURCHASE_ORDER,
            ActivityLog::ACTION_CREATED,
            "Purchase order {$order->order_number} was created for {$order->supplier} with {$order->items->count()} item(s).",
            $order,
        );

        $this->notifyOppositeRole(
            'Purchase order',
            $order->order_number,
            'created',
            auth()->user(),
            route('purchase-orders.show', $order),
        );

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order created. Receive it when the stock arrives.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.product.brand', 'items.product.category', 'user']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->isDraft(), 403, 'Only draft purchase orders can be edited.');

        return view('purchase-orders.form', [
            'order' => $purchaseOrder,
            'items' => $purchaseOrder->items,
            'products' => Product::orderBy('name')->get(),
            'action' => route('purchase-orders.update', $purchaseOrder),
            'method' => 'PUT',
            'editing' => true,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 403, 'Only draft purchase orders can be edited.');
        $data = $request->validated();

        DB::transaction(function () use ($data, $purchaseOrder) {
            $purchaseOrder->update([
                'order_number' => ($data['order_number'] ?? null) ?: $purchaseOrder->order_number,
                'supplier' => $data['supplier'],
                'order_date' => $data['order_date'],
                'expected_date' => ($data['expected_date'] ?? null) ?: null,
                'notes' => ($data['notes'] ?? null) ?: null,
            ]);

            $this->saveItems($purchaseOrder, $data['items']);
        });

        ActivityLog::record(
            ActivityLog::MODULE_PURCHASE_ORDER,
            ActivityLog::ACTION_UPDATED,
            "Purchase order {$purchaseOrder->order_number} was updated.",
            $purchaseOrder,
        );

        $this->notifyOppositeRole(
            'Purchase order',
            $purchaseOrder->order_number,
            'updated',
            auth()->user(),
            route('purchase-orders.show', $purchaseOrder),
        );

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 403, 'Only draft purchase orders can be deleted.');
        $number = $purchaseOrder->order_number;

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();
        });

        ActivityLog::record(
            ActivityLog::MODULE_PURCHASE_ORDER,
            ActivityLog::ACTION_CANCELLED,
            "Draft purchase order {$number} was deleted.",
        );

        $this->notifyOppositeRole(
            'Purchase order',
            $number,
            'deleted',
            auth()->user(),
            route('purchase-orders.index'),
        );

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Draft purchase order deleted.');
    }

    /** Receiving stock turns every draft item into a stock-in movement. */
    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 403, 'This purchase order has already been processed.');
        abort_if($purchaseOrder->items()->doesntExist(), 422, 'A purchase order needs at least one item before it can be received.');

        DB::transaction(function () use ($purchaseOrder) {
            foreach ($purchaseOrder->items as $item) {
                InventoryTransaction::create([
                    'product_id' => $item->product_id,
                    'type' => InventoryTransaction::TYPE_IN,
                    'quantity' => $item->quantity,
                    'reference' => $purchaseOrder->order_number,
                    'reason' => 'Purchase order received',
                    'notes' => "Received from {$purchaseOrder->supplier}",
                    'user_id' => auth()->id(),
                ]);
            }

            $purchaseOrder->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
        });

        ActivityLog::record(
            ActivityLog::MODULE_PURCHASE_ORDER,
            ActivityLog::ACTION_RECEIVED,
            "Purchase order {$purchaseOrder->order_number} was received. {$purchaseOrder->totalQuantity()} unit(s) were added to stock.",
            $purchaseOrder,
        );

        $this->notifyOppositeRole(
            'Purchase order',
            $purchaseOrder->order_number,
            'received',
            auth()->user(),
            route('purchase-orders.show', $purchaseOrder),
        );

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order received and stock levels updated.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 403, 'Only draft purchase orders can be cancelled.');

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        ActivityLog::record(
            ActivityLog::MODULE_PURCHASE_ORDER,
            ActivityLog::ACTION_CANCELLED,
            "Purchase order {$purchaseOrder->order_number} was cancelled.",
            $purchaseOrder,
        );

        $this->notifyOppositeRole(
            'Purchase order',
            $purchaseOrder->order_number,
            'cancelled',
            auth()->user(),
            route('purchase-orders.show', $purchaseOrder),
        );

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order cancelled.');
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function saveItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ]);
        }
    }
}
