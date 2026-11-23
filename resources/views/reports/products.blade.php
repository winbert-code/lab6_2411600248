@extends('layouts.app')

@section('title', 'Product Report')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">Product Report</h1>
        <p class="text-muted mb-0">Stock, valuation and movement totals per computer part.</p>
    </div>

    <div class="card app-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.products') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="search" class="form-label small fw-semibold">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="{{ $search }}" placeholder="Product name or SKU…">
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small fw-semibold">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All products</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="from" class="form-label small fw-semibold">Movements from</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="to" class="form-label small fw-semibold">Movements to</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                    <a href="{{ route('reports.products') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card card h-100">
                <div class="card-body">
                    <p class="stat-label mb-1">Products reported</p>
                    <h2 class="stat-value mb-0">{{ number_format($productCount) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card h-100">
                <div class="card-body">
                    <p class="stat-label mb-1">Units on hand</p>
                    <h2 class="stat-value mb-0">{{ number_format($unitsOnHand) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card h-100">
                <div class="card-body">
                    <p class="stat-label mb-1">Stock value</p>
                    <h2 class="stat-value mb-0">&#8369;{{ number_format((float) $stockValue, 2) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card app-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Stock</th>
                        <th class="text-end">On hand</th>
                        <th class="text-end">Reorder</th>
                        <th class="text-end">Value</th>
                        <th class="text-end">In</th>
                        <th class="text-end">Out</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <a href="{{ route('products.show', $product) }}" class="text-decoration-none fw-semibold">
                                    {{ $product->name }}
                                </a>
                                <div class="small text-muted">{{ $product->sku }}</div>
                            </td>
                            <td>{{ $product->category?->name ?? '—' }}</td>
                            <td>{{ $product->brand?->name ?? '—' }}</td>
                            <td><x-stock-badge :status="$product->availabilityStatus()" /></td>
                            <td class="text-end">{{ number_format($product->quantity) }}</td>
                            <td class="text-end">{{ number_format($product->reorder_level) }}</td>
                            <td class="text-end">&#8369;{{ number_format((float) $product->stockValue(), 2) }}</td>
                            <td class="text-end text-success">{{ number_format($product->units_in) }}</td>
                            <td class="text-end text-danger">{{ number_format($product->units_out) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-clock-history"></i> History
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No products match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">{{ $products->links('pagination::bootstrap-5') }}</div>
    </div>
@endsection
