@extends('layouts.app')

@section('title', 'Not found')

@section('content')
    <div class="card">
        <h1>Not found</h1>
        <p class="muted">That page does not exist.</p>
        <a href="{{ url('/') }}">Back to the dashboard</a>
    </div>
@endsection
