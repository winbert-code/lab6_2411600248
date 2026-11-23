@extends('layouts.app')

@section('title', 'Daily Report History')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">Daily Report History</h1>
        <p class="text-muted mb-0">A day-by-day history of stock movements, sales and purchases.</p>
    </div>

    <div class="card app-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.daily') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="from" class="form-label small fw-semibold">From</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                </div>
                <div class="col-12 col-md-4">
                    <label for="to" class="form-label small fw-semibold">To</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                    <a href="{{ route('reports.daily') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card app-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-center">Movements</th>
                        <th class="text-end">Units in</th>
                        <th class="text-end">Units out</th>
                        <th class="text-center">Sales orders</th>
                        <th class="text-end">Sales total</th>
                        <th class="text-center">Purchase orders</th>
                        <th class="text-end">Purchase total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr>
                            <td class="text-nowrap fw-semibold">
                                {{ \Illuminate\Support\Carbon::parse($day['date'])->format('M d, Y') }}
                            </td>
                            <td class="text-center">{{ number_format($day['movements']) }}</td>
                            <td class="text-end text-success">{{ number_format($day['units_in']) }}</td>
                            <td class="text-end text-danger">{{ number_format($day['units_out']) }}</td>
                            <td class="text-center">{{ number_format($day['sales_orders']) }}</td>
                            <td class="text-end">&#8369;{{ number_format((float) $day['sales_total'], 2) }}</td>
                            <td class="text-center">{{ number_format($day['purchase_orders']) }}</td>
                            <td class="text-end">&#8369;{{ number_format((float) $day['purchase_total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No daily activity in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
