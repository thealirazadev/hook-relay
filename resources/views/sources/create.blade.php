@extends('layouts.app')

@section('title', 'New source')

@section('content')
    <h1>New source</h1>

    <div class="card">
        <form method="POST" action="{{ url('/sources') }}" class="stack">
            @csrf

            <div class="field @error('name') has-error @enderror">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="255" required>
                <p class="help">A label for your own reference, e.g. "Stripe production".</p>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field @error('provider') has-error @enderror">
                <label for="provider">Provider</label>
                <select id="provider" name="provider" required>
                    <option value="" disabled {{ old('provider') ? '' : 'selected' }}>Select a provider</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider }}" {{ old('provider') === $provider ? 'selected' : '' }}>{{ $provider }}</option>
                    @endforeach
                </select>
                <p class="help">Determines the signature scheme. Cannot be changed after creation.</p>
                @error('provider')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field @error('signing_secret') has-error @enderror">
                <label for="signing_secret">Signing secret</label>
                <input type="password" id="signing_secret" name="signing_secret" autocomplete="off" required>
                <p class="help">The provider's webhook signing secret. Stored encrypted; not shown again after saving.</p>
                @error('signing_secret')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Create source</button>
                <a href="{{ url('/sources') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
