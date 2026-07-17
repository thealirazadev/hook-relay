<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Models\WebhookEvent;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'source_id' => $request->query('source_id'),
            'event_type' => $request->query('event_type'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'q' => $request->query('q'),
        ];

        $query = WebhookEvent::query()->with('source')->latest('received_at');

        if (is_numeric($filters['source_id'])) {
            $query->where('source_id', (int) $filters['source_id']);
        }

        if (filled($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (filled($filters['q'])) {
            $query->where('provider_event_id', 'like', '%'.$filters['q'].'%');
        }

        if ($from = $this->parseDate($filters['from'])) {
            $query->where('received_at', '>=', $from->startOfDay());
        }

        if ($to = $this->parseDate($filters['to'])) {
            $query->where('received_at', '<=', $to->endOfDay());
        }

        return view('events.index', [
            'events' => $query->paginate(25)->withQueryString(),
            'sources' => Source::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(WebhookEvent $event): View
    {
        $event->load(['source', 'deliveries.destination']);

        return view('events.show', ['event' => $event]);
    }

    private function parseDate(?string $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }
}
