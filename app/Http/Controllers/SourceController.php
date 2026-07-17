<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        $sources = Source::orderBy('name')->get();

        return view('sources.index', ['sources' => $sources]);
    }

    public function create(): View
    {
        return view('sources.create', ['providers' => Source::PROVIDERS]);
    }

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        $source = Source::create($request->validated());

        return redirect('/sources')
            ->with('status', "Source \"{$source->name}\" created. Its ingest URL is shown below.");
    }

    public function edit(Source $source): View
    {
        return view('sources.edit', ['source' => $source]);
    }

    public function update(UpdateSourceRequest $request, Source $source): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'active']);

        if ($request->filled('signing_secret')) {
            $data['signing_secret'] = $request->input('signing_secret');
        }

        $source->update($data);

        return redirect('/sources')->with('status', "Source \"{$source->name}\" updated.");
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect('/sources')->with('status', "Source \"{$source->name}\" deleted.");
    }
}
