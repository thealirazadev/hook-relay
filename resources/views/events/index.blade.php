@extends('layouts.app')

@section('title', 'Events')

@php($hasFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())

@section('content')
    <h1>Events</h1>

    <div class="card">
        <form method="GET" action="{{ url('/events') }}" class="filters">
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
                <label for="event_type">Event type</label>
                <input type="text" id="event_type" name="event_type" value="{{ $filters['event_type'] }}">
            </div>
            <div class="field">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}">
            </div>
            <div class="field">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}">
            </div>
            <div class="field">
                <label for="q">Provider event id</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] }}">
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                @if ($hasFilters)
                    <a href="{{ url('/events') }}" class="btn btn-secondary">Clear filters</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        @if ($events->isEmpty())
            <div class="empty">
                @if ($hasFilters)
                    <p>No events match these filters.</p>
                    <a href="{{ url('/events') }}" class="btn btn-secondary">Clear filters</a>
                @else
                    <p>No events yet. Point a provider webhook at a source's ingest URL.</p>
                @endif
            </div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Received</th>
                            <th scope="col">Source</th>
                            <th scope="col">Type</th>
                            <th scope="col">Provider event id</th>
                            <th scope="col">Event</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td class="muted small">{{ $event->received_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $event->source?->name ?? '—' }}</td>
                                <td class="truncate" title="{{ $event->event_type }}">{{ $event->event_type ?? '—' }}</td>
                                <td class="mono truncate" title="{{ $event->provider_event_id }}">{{ $event->provider_event_id ?? '—' }}</td>
                                <td class="mono"><a href="{{ url('/events/'.$event->id) }}">{{ Str::limit($event->id, 12, '…') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $events])
        @endif
    </div>
@endsection
