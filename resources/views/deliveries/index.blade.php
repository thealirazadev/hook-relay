@extends('layouts.app')

@section('title', 'Deliveries')

@php($hasFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())

@section('content')
    <h1>Deliveries</h1>

    <div class="card">
        <form method="GET" action="{{ url('/deliveries') }}" class="filters">
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="source_id">Source</label>
                <select id="source_id" name="source_id">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" {{ (string) $filters['source_id'] === (string) $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="destination_id">Destination</label>
                <select id="destination_id" name="destination_id">
                    <option value="">All destinations</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}" {{ (string) $filters['destination_id'] === (string) $destination->id ? 'selected' : '' }}>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                @if ($hasFilters)
                    <a href="{{ url('/deliveries') }}" class="btn btn-secondary">Clear filters</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        @if ($deliveries->isEmpty())
            <div class="empty">
                @if ($hasFilters)
                    <p>No deliveries match these filters.</p>
                    <a href="{{ url('/deliveries') }}" class="btn btn-secondary">Clear filters</a>
                @else
                    <p>No deliveries yet. They appear once an event is routed to a destination.</p>
                @endif
            </div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Updated</th>
                            <th scope="col">Source</th>
                            <th scope="col">Destination</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="num">Attempts</th>
                            <th scope="col">Delivery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td class="muted small">{{ $delivery->updated_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $delivery->event?->source?->name ?? '—' }}</td>
                                <td class="truncate" title="{{ $delivery->destination?->url }}">{{ $delivery->destination?->name ?? '—' }}</td>
                                <td>@include('partials.badge', ['status' => $delivery->status])</td>
                                <td class="num">{{ $delivery->attempt_count }}</td>
                                <td class="mono"><a href="{{ url('/deliveries/'.$delivery->id) }}">{{ Str::limit($delivery->id, 12, '…') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $deliveries])
        @endif
    </div>
@endsection
