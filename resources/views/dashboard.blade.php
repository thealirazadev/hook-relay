@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>

    <div class="stats">
        @foreach ($counts as $status => $count)
            <div class="stat">
                <div class="n">{{ $count }}</div>
                <div class="k">@include('partials.badge', ['status' => $status])</div>
            </div>
        @endforeach
    </div>

    @if ($deadCount > 0)
        <div class="card">
            <p><strong>{{ $deadCount }}</strong> dead {{ Str::plural('delivery', $deadCount) }} waiting in the
                <a href="{{ url('/dlq') }}">dead-letter queue</a>.</p>
        </div>
    @endif

    <div class="header-row">
        <h2>Recent events</h2>
        <a href="{{ url('/events') }}" class="btn btn-secondary btn-sm">All events</a>
    </div>

    <div class="card">
        @if ($recentEvents->isEmpty())
            <div class="empty">
                <p>No events yet. Point a provider webhook at a source's ingest URL to see events here.</p>
                <a href="{{ url('/sources') }}" class="btn btn-primary">Manage sources</a>
            </div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Received</th>
                            <th scope="col">Source</th>
                            <th scope="col">Type</th>
                            <th scope="col">Event id</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentEvents as $event)
                            <tr>
                                <td class="muted small">{{ $event->received_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $event->source?->name ?? '—' }}</td>
                                <td class="truncate" title="{{ $event->event_type }}">{{ $event->event_type ?? '—' }}</td>
                                <td class="mono">
                                    <a href="{{ url('/events/'.$event->id) }}">{{ Str::limit($event->id, 12, '…') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
