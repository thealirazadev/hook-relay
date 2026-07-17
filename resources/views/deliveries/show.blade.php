@extends('layouts.app')

@section('title', 'Delivery')

@section('content')
    <div class="header-row">
        <h1>Delivery</h1>
        <div class="actions">
            @if ($delivery->status === \App\Models\Delivery::STATUS_DEAD)
                <form method="POST" action="{{ url('/dlq/'.$delivery->id.'/requeue') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Requeue</button>
                </form>
            @endif
            @if ($delivery->event)
                <a href="{{ url('/events/'.$delivery->event->id) }}" class="btn btn-secondary">View event</a>
            @endif
        </div>
    </div>

    <div class="card">
        <dl class="meta">
            <dt>Delivery id</dt>
            <dd class="mono">{{ $delivery->id }}</dd>
            <dt>Status</dt>
            <dd>@include('partials.badge', ['status' => $delivery->status])</dd>
            <dt>Source</dt>
            <dd>{{ $delivery->event?->source?->name ?? '—' }}</dd>
            <dt>Destination</dt>
            <dd>{{ $delivery->destination?->name ?? '—' }} <span class="mono muted small">{{ $delivery->destination?->url }}</span></dd>
            <dt>Attempts</dt>
            <dd>{{ $delivery->attempt_count }} of {{ $delivery->max_attempts }}</dd>
            <dt>Last attempt</dt>
            <dd class="muted">{{ $delivery->last_attempted_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
            <dt>Next attempt</dt>
            <dd class="muted">{{ $delivery->next_attempt_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
        </dl>
    </div>

    <h2>Attempt history</h2>
    <div class="card">
        @if ($delivery->attempts->isEmpty())
            <div class="empty"><p>No attempts recorded yet.</p></div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="num">#</th>
                            <th scope="col" class="num">Status</th>
                            <th scope="col" class="num">Duration</th>
                            <th scope="col">When</th>
                            <th scope="col">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($delivery->attempts as $attempt)
                            <tr>
                                <td class="num">{{ $attempt->attempt_number }}</td>
                                <td class="num">{{ $attempt->response_status ?? '—' }}</td>
                                <td class="num">{{ $attempt->duration_ms }} ms</td>
                                <td class="muted small">{{ $attempt->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    @if ($attempt->error)
                                        <span class="error small">{{ $attempt->error }}</span>
                                    @else
                                        <details>
                                            <summary class="small">Response body</summary>
                                            <pre class="code">{{ $attempt->response_body_excerpt }}</pre>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
