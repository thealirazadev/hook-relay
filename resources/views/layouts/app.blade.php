<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'hook-relay') &middot; {{ config('app.name', 'hook-relay') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="bar">
            <a href="{{ url('/') }}" class="brand">hook-relay</a>
            @auth
                <nav class="site-nav" aria-label="Primary">
                    <a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}">Dashboard</a>
                    <a href="{{ url('/sources') }}" class="{{ request()->is('sources*') ? 'active' : '' }}">Sources</a>
                    <a href="{{ url('/destinations') }}" class="{{ request()->is('destinations*') ? 'active' : '' }}">Destinations</a>
                    <a href="{{ url('/events') }}" class="{{ request()->is('events*') ? 'active' : '' }}">Events</a>
                    <a href="{{ url('/deliveries') }}" class="{{ request()->is('deliveries*') ? 'active' : '' }}">Deliveries</a>
                    <a href="{{ url('/dlq') }}" class="{{ request()->is('dlq*') ? 'active' : '' }}">Dead letters</a>
                </nav>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Log out</button>
                </form>
            @endauth
        </div>
    </header>

    @if (session('status') || session('error'))
        <div class="flash">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error" role="alert">{{ session('error') }}</div>
            @endif
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        hook-relay &middot; self-hosted webhook gateway
    </footer>
</body>
</html>
