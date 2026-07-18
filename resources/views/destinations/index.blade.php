@extends('layouts.app')

@section('title', 'Destinations')

@section('content')
    <div class="header-row">
        <h1>Destinations</h1>
        <a href="{{ url('/destinations/create') }}" class="btn btn-primary">New destination</a>
    </div>

    @if ($destinations->isEmpty())
        <div class="card">
            <div class="empty">
                <p>No destinations yet. Create one, then route it to a source.</p>
                <a href="{{ url('/destinations/create') }}" class="btn btn-primary">Create a destination</a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">URL</th>
                            <th scope="col">Active</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($destinations as $destination)
                            <tr>
                                <td>{{ $destination->name }}</td>
                                <td class="mono truncate" title="{{ $destination->url }}">{{ $destination->url }}</td>
                                <td>{{ $destination->active ? 'yes' : 'no' }}</td>
                                <td>
                                    <div class="actions">
                                        <a href="{{ url('/destinations/'.$destination->id.'/edit') }}" class="btn btn-secondary btn-sm">Edit</a>
                                        <details class="confirm">
                                            <summary class="btn btn-danger btn-sm">Delete</summary>
                                            <form method="POST" action="{{ url('/destinations/'.$destination->id) }}" class="confirm-body">
                                                @csrf
                                                @method('DELETE')
                                                <span class="small">History is kept; no new deliveries.</span>
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
