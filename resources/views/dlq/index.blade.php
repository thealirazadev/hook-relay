@extends('layouts.app')

@section('title', 'Dead letters')

@section('content')
    <div class="header-row">
        <h1>Dead letters</h1>
        @if ($deliveries->total() > 0)
            <details class="confirm">
                <summary class="btn btn-danger">Requeue all</summary>
                <form method="POST" action="{{ url('/dlq/requeue-all') }}" class="confirm-body">
                    @csrf
                    <span class="small">Requeue every dead delivery?</span>
                    <button type="submit" class="btn btn-danger">Confirm requeue all</button>
                </form>
            </details>
        @endif
    </div>

    @if ($deliveries->isEmpty())
        <div class="card">
            <div class="empty">
                <p>No dead deliveries. Everything that failed has either been delivered or is still retrying.</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Delivery</th>
                            <th scope="col">Source</th>
                            <th scope="col">Destination</th>
                            <th scope="col" class="num">Attempts</th>
                            <th scope="col">Last attempt</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td class="mono">
                                    <a href="{{ url('/deliveries/'.$delivery->id) }}">{{ Str::limit($delivery->id, 12, '…') }}</a>
                                </td>
                                <td>{{ $delivery->event?->source?->name ?? '—' }}</td>
                                <td class="mono truncate" title="{{ $delivery->destination?->url }}">{{ $delivery->destination?->name ?? '—' }}</td>
                                <td class="num">{{ $delivery->attempt_count }}</td>
                                <td class="muted small">{{ $delivery->last_attempted_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/dlq/'.$delivery->id.'/requeue') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Requeue</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $deliveries])
        </div>
    @endif
@endsection
