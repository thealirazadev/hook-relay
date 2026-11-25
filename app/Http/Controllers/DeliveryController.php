<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'status' => $this->filterValue($request, 'status'),
            'source_id' => $this->filterValue($request, 'source_id'),
            'destination_id' => $this->filterValue($request, 'destination_id'),
        ];

        $query = Delivery::with(['destination', 'event.source'])->latest('updated_at');

        if (in_array($filters['status'], Delivery::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if (is_numeric($filters['source_id'])) {
            $query->whereHas('event', fn ($q) => $q->where('source_id', (int) $filters['source_id']));
        }

        if (is_numeric($filters['destination_id'])) {
            $query->where('destination_id', (int) $filters['destination_id']);
        }

        return view('deliveries.index', [
            'deliveries' => $query->paginate(25)->withQueryString(),
            'sources' => Source::orderBy('name')->get(),
            'destinations' => Destination::orderBy('name')->get(),
            'statuses' => Delivery::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function show(Delivery $delivery): View
    {
        $delivery->load(['destination', 'event.source', 'attempts' => fn ($q) => $q->orderByDesc('attempt_number')]);

        return view('deliveries.show', ['delivery' => $delivery]);
    }
}
