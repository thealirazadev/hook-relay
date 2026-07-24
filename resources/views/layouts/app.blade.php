<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'hook-relay') &middot; {{ config('app.name', 'hook-relay') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>
    <header class="site-header">
        <div class="bar">
            <a href="{{ url('/') }}" class="brand">hook-relay</a>
            @auth
                <nav class="site-nav" aria-label="Primary">
                    <a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}" @if (request()->is('/')) aria-current="page" @endif>Dashboard</a>
                    <a href="{{ url('/sources') }}" class="{{ request()->is('sources*') ? 'active' : '' }}" @if (request()->is('sources*')) aria-current="page" @endif>Sources</a>
                    <a href="{{ url('/destinations') }}" class="{{ request()->is('destinations*') ? 'active' : '' }}" @if (request()->is('destinations*')) aria-current="page" @endif>Destinations</a>
                    <a href="{{ url('/events') }}" class="{{ request()->is('events*') ? 'active' : '' }}" @if (request()->is('events*')) aria-current="page" @endif>Events</a>
                    <a href="{{ url('/deliveries') }}" class="{{ request()->is('deliveries*') ? 'active' : '' }}" @if (request()->is('deliveries*')) aria-current="page" @endif>Deliveries</a>
                    <a href="{{ url('/dlq') }}" class="{{ request()->is('dlq*') ? 'active' : '' }}" @if (request()->is('dlq*')) aria-current="page" @endif>Dead letters</a>
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

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="site-footer">
        hook-relay &middot; self-hosted webhook gateway
    </footer>
</body>
</html>
