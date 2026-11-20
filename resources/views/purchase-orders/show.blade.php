@extends('layouts.app')

@section('title', 'Purchase Order '.$order->order_number)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">{{ $order->order_number }}</h1>
            <p class="text-muted mb-0">Created {{ $order->created_at?->format('F d, Y h:i A') }} by {{ $order->user?->name ?? 'System' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to list
            </a>
            @can('create-orders')
                @if ($order->isDraft())
                    <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                @endif
            @endcan
            @can('fulfil-orders')
                @if ($order->isDraft())
                    <form method="POST" action="{{ route('purchase-orders.receive', $order) }}"
                          onsubmit="return confirm('Receive this order? Stock levels will increase immediately.')">
                        @csrf
                        <button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-down"></i> Receive stock</button>
                    </form>
                    <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}"
                          onsubmit="return confirm('Cancel this purchase order?')">
                        @csrf
                        <button class="btn btn-outline-warning" type="submit">Cancel order</button>
                    </form>
                    <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}"
                          onsubmit="return confirm('Delete this draft order permanently?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card app-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h2 class="h5 fw-bold mb-0">Order details</h2>
                </div>
                <div class="card-body px-4">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7"><span class="badge bg-{{ $order->status_color }}">{{ $order->status_label }}</span></dd>
                        <dt class="col-5 text-muted">Supplier</dt>
                        <dd class="col-7">{{ $order->supplier }}</dd>
                        <dt class="col-5 text-muted">Order date</dt>
                        <dd class="col-7">{{ $order->order_date?->format('F d, Y') }}</dd>
                        <dt class="col-5 text-muted">Expected date</dt>
                        <dd class="col-7">{{ $order->expected_date?->format('F d, Y') ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Items</dt>
                        <dd class="col-7">{{ $order->items->count() }} lines / {{ $order->totalQuantity() }} units</dd>
                        <dt class="col-5 text-muted">Total</dt>
                        <dd class="col-7 fw-bold">₱{{ number_format($order->totalAmount(), 2) }}</dd>
                        <dt class="col-5 text-muted">Notes</dt>
                        <dd class="col-7">{{ $order->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card app-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h2 class="h5 fw-bold mb-0">Ordered items</h2>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit cost</th>
                                <th class="text-end">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->items as $item)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.show', $item->product_id) }}" class="text-decoration-none fw-semibold">
                                            {{ $item->product?->name ?? 'Deleted product' }}
                                        </a>
                                        <div class="small text-muted">{{ $item->product?->sku }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format($item->quantity) }}</td>
                                    <td class="text-end">₱{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-end fw-semibold">₱{{ number_format($item->lineTotal(), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No items on this order.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">Order total</td>
                                <td class="text-end">₱{{ number_format($order->totalAmount(), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
