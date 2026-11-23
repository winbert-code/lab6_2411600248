@extends('layouts.app')

@section('title', 'Sales & Purchase Report')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">Sales &amp; Purchase Report</h1>
        <p class="text-muted mb-0">Order totals and item lines for sales and purchasing.</p>
    </div>

    <div class="card app-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.orders') }}" class="row g-3 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="from" class="form-label small fw-semibold">From</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $filters['from'] }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="to" class="form-label small fw-semibold">To</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $filters['to'] }}">
                </div>
                <div class="col-6 col-md-3">
                    <label for="sales_status" class="form-label small fw-semibold">Sales status</label>
                    <select class="form-select" id="sales_status" name="sales_status">
                        <option value="">All sales statuses</option>
                        @foreach ($salesStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sales_status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="purchase_status" class="form-label small fw-semibold">Purchase status</label>
                    <select class="form-select" id="purchase_status" name="purchase_status">
                        <option value="">All purchase statuses</option>
                        @foreach ($purchaseStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['purchase_status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                    <a href="{{ route('reports.orders') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card card h-100">
                <div class="card-body">
                    <p class="stat-label mb-1">Sales orders</p>
                    <h2 class="stat-value mb-0">{{ number_format($salesCount) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card h-100">
                <div class="card-body">
                    <p class="stat-label mb-1">Sales order value</p>
                    <h2 class="stat-value mb-0">&#8369;{{ number_format((float) $salesTotal, 2) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card h-100">
                <div class="card-body">
                    <p class="stat-label mb-1">Purchase order value</p>
                    <h2 class="stat-value mb-0">&#8369;{{ number_format((float) $purchaseTotal, 2) }}</h2>
                    <span class="stat-link">{{ number_format($purchaseCount) }} purchase order(s)</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card app-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h2 class="h5 fw-bold mb-0">Sales orders</h2>
                    <p class="text-muted small mb-0">Customer orders within the selected filters.</p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($salesOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('sales-orders.show', $order) }}" class="text-decoration-none fw-semibold">
                                            {{ $order->order_number }}
                                        </a>
                                        <div class="small text-muted">{{ $order->user?->name ?? 'System' }}</div>
                                    </td>
                                    <td>{{ $order->customer }}</td>
                                    <td class="text-nowrap">{{ $order->order_date?->format('M d, Y') }}</td>
                                    <td class="text-center">{{ $order->items->count() }}</td>
                                    <td class="text-end fw-semibold">&#8369;{{ number_format($order->totalAmount(), 2) }}</td>
                                    <td><span class="badge bg-{{ $order->status_color }}">{{ $order->status_label }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No sales orders in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">{{ $salesOrders->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card app-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h2 class="h5 fw-bold mb-0">Purchase orders</h2>
                    <p class="text-muted small mb-0">Supplier orders within the selected filters.</p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase-orders.show', $order) }}" class="text-decoration-none fw-semibold">
                                            {{ $order->order_number }}
                                        </a>
                                        <div class="small text-muted">{{ $order->user?->name ?? 'System' }}</div>
                                    </td>
                                    <td>{{ $order->supplier }}</td>
                                    <td class="text-nowrap">{{ $order->order_date?->format('M d, Y') }}</td>
                                    <td class="text-center">{{ $order->items->count() }}</td>
                                    <td class="text-end fw-semibold">&#8369;{{ number_format($order->totalAmount(), 2) }}</td>
                                    <td><span class="badge bg-{{ $order->status_color }}">{{ $order->status_label }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No purchase orders in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">{{ $purchaseOrders->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    </div>
@endsection
