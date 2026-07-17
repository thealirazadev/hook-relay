@extends('layouts.app')

@section('title', 'Something went wrong')

@section('content')
    <div class="card">
        <h1>Something went wrong</h1>
        <p class="muted">An unexpected error occurred. The details have been logged.</p>
        <a href="{{ url('/') }}">Back to the dashboard</a>
    </div>
@endsection
