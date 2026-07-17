@extends('layouts.app')

@section('title', 'Event')

@section('content')
    <div class="header-row">
        <h1>Event</h1>
    </div>

    <div class="card">
        <dl class="meta">
            <dt>Event id</dt>
            <dd class="mono">{{ $event->id }}</dd>
            <dt>Source</dt>
            <dd>{{ $event->source?->name ?? '—' }} <span class="muted small">({{ $event->source?->provider }})</span></dd>
            <dt>Event type</dt>
            <dd>{{ $event->event_type ?? '—' }}</dd>
            <dt>Provider event id</dt>
            <dd class="mono">{{ $event->provider_event_id ?? '—' }}</dd>
            <dt>Content type</dt>
            <dd class="mono">{{ $event->content_type ?? '—' }}</dd>
            <dt>Received</dt>
            <dd class="muted">{{ $event->received_at?->format('Y-m-d H:i:s') }}</dd>
        </dl>
    </div>

    <h2>Payload</h2>
    <div class="card">
        <pre class="code">{{ $event->payload }}</pre>
    </div>

    <h2>Stored headers</h2>
    <div class="card">
        @if (empty($event->headers))
            <div class="empty"><p>No headers stored.</p></div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th scope="col">Header</th><th scope="col">Value</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($event->headers as $name => $value)
                            <tr>
                                <td class="mono">{{ $name }}</td>
                                <td class="mono">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <h2>Deliveries</h2>
    <div class="card">
        @if ($event->deliveries->isEmpty())
            <div class="empty"><p>No deliveries. The source may have no routed destinations.</p></div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Destination</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="num">Attempts</th>
                            <th scope="col">Delivery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($event->deliveries as $delivery)
                            <tr>
                                <td>{{ $delivery->destination?->name ?? '—' }}</td>
                                <td>@include('partials.badge', ['status' => $delivery->status])</td>
                                <td class="num">{{ $delivery->attempt_count }}</td>
                                <td class="mono"><a href="{{ url('/deliveries/'.$delivery->id) }}">{{ Str::limit($delivery->id, 12, '…') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
