@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>
    <div class="card">
        <p class="muted">Welcome to hook-relay. Register a source to receive webhooks, then attach
            destinations to forward them.</p>
        <div class="actions">
            <a href="{{ url('/sources') }}" class="btn btn-primary">Manage sources</a>
            <a href="{{ url('/destinations') }}" class="btn btn-secondary">Manage destinations</a>
        </div>
    </div>
@endsection
