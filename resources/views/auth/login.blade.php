@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="login-wrap">
        <div class="card">
            <h1>Log in</h1>
            <form method="POST" action="{{ url('/login') }}" class="stack">
                @csrf
                <div class="field @error('email') has-error @enderror">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           autocomplete="username" autofocus required>
                    @error('email')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field @error('password') has-error @enderror">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" required>
                    @error('password')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Log in</button>
                </div>
            </form>
        </div>
    </div>
@endsection
