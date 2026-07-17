@extends('layouts.app')

@section('title', 'Edit source')

@section('content')
    <h1>Edit source</h1>

    @php($ingestUrl = rtrim(config('app.url'), '/').'/ingest/'.$source->ingest_key)

    <div class="card">
        <dl class="meta">
            <dt>Provider</dt>
            <dd>{{ $source->provider }}</dd>
            <dt>Ingest URL</dt>
            <dd class="mono">{{ $ingestUrl }}</dd>
        </dl>
    </div>

    <div class="card">
        <form method="POST" action="{{ url('/sources/'.$source->id) }}" class="stack">
            @csrf
            @method('PUT')

            <div class="field @error('name') has-error @enderror">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $source->name) }}" maxlength="255" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field @error('signing_secret') has-error @enderror">
                <label for="signing_secret">Signing secret</label>
                <input type="password" id="signing_secret" name="signing_secret" autocomplete="off"
                       placeholder="Leave blank to keep the current secret">
                <p class="help">Enter a new secret only if you want to replace it. The current secret is never shown.</p>
                @error('signing_secret')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="active" value="1" {{ old('active', $source->active) ? 'checked' : '' }}>
                    Active (inactive sources reject ingest with 404)
                </label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ url('/sources') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
