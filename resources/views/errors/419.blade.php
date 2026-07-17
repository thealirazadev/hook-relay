@extends('layouts.app')

@section('title', 'Page expired')

@section('content')
    <div class="card">
        <h1>Page expired</h1>
        <p class="muted">Your session expired before the form was submitted. Please try again.</p>
        <a href="{{ url('/') }}">Back to the dashboard</a>
    </div>
@endsection
