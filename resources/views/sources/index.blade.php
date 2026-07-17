@extends('layouts.app')

@section('title', 'Sources')

@section('content')
    <div class="header-row">
        <h1>Sources</h1>
        <a href="{{ url('/sources/create') }}" class="btn btn-primary">New source</a>
    </div>

    @if ($sources->isEmpty())
        <div class="card">
            <div class="empty">
                <p>No sources yet. Create your first source to get an ingest URL.</p>
                <a href="{{ url('/sources/create') }}" class="btn btn-primary">Create a source</a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Provider</th>
                            <th scope="col">Ingest URL</th>
                            <th scope="col">Active</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sources as $source)
                            @php($ingestUrl = rtrim(config('app.url'), '/').'/ingest/'.$source->ingest_key)
                            <tr>
                                <td>{{ $source->name }}</td>
                                <td>{{ $source->provider }}</td>
                                <td class="mono truncate" title="{{ $ingestUrl }}">{{ $ingestUrl }}</td>
                                <td>{{ $source->active ? 'yes' : 'no' }}</td>
                                <td>
                                    <div class="actions">
                                        <a href="{{ url('/sources/'.$source->id.'/edit') }}" class="btn btn-secondary btn-sm">Edit</a>
                                        <details class="confirm">
                                            <summary class="btn btn-danger btn-sm">Delete</summary>
                                            <form method="POST" action="{{ url('/sources/'.$source->id) }}" class="confirm-body">
                                                @csrf
                                                @method('DELETE')
                                                <span class="small">Its ingest URL will stop working.</span>
                                                <button type="submit" class="btn btn-danger btn-sm">Confirm delete</button>
                                            </form>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
