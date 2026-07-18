@extends('layouts.app')

@section('title', 'Edit destination')

@section('content')
    <h1>Edit destination</h1>

    <div class="card">
        <form method="POST" action="{{ url('/destinations/'.$destination->id) }}" class="stack">
            @csrf
            @method('PUT')

            <div class="field @error('name') has-error @enderror">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $destination->name) }}" maxlength="255" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field @error('url') has-error @enderror">
                <label for="url">URL</label>
                <input type="url" id="url" name="url" value="{{ old('url', $destination->url) }}" maxlength="2048" required>
                <p class="help">Must be an http or https URL.</p>
                @error('url')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="active" value="1" {{ old('active', $destination->active) ? 'checked' : '' }}>
                    Active (inactive destinations get no new deliveries)
                </label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ url('/destinations') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
