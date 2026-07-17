<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDestinationRequest;
use App\Http\Requests\UpdateDestinationRequest;
use App\Models\Destination;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function index(): View
    {
        $destinations = Destination::orderBy('name')->get();

        return view('destinations.index', ['destinations' => $destinations]);
    }

    public function create(): View
    {
        return view('destinations.create');
    }

    public function store(StoreDestinationRequest $request): RedirectResponse
    {
        $destination = Destination::create($request->validated());

        return redirect('/destinations')->with('status', "Destination \"{$destination->name}\" created.");
    }

    public function edit(Destination $destination): View
    {
        return view('destinations.edit', ['destination' => $destination]);
    }

    public function update(UpdateDestinationRequest $request, Destination $destination): RedirectResponse
    {
        $destination->update($request->validated());

        return redirect('/destinations')->with('status', "Destination \"{$destination->name}\" updated.");
    }

    public function destroy(Destination $destination): RedirectResponse
    {
        $destination->delete();

        return redirect('/destinations')->with('status', "Destination \"{$destination->name}\" deleted.");
    }
}
