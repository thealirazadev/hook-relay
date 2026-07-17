<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkReplayRequest;
use App\Models\Source;
use App\Models\WebhookEvent;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function replay(WebhookEvent $event): RedirectResponse
    {
        $deliveries = $event->createDeliveries();

        Log::info('event.replayed', ['event_id' => $event->id, 'deliveries' => $deliveries->count()]);

        $message = $deliveries->isEmpty()
            ? 'Replayed, but the source has no active routed destinations, so no deliveries were created.'
            : 'Replayed to '.$deliveries->count().' '.Str::plural('destination', $deliveries->count()).'.';

        return redirect('/events/'.$event->id)->with('status', $message);
    }

    public function bulkReplay(BulkReplayRequest $request): RedirectResponse
    {
        $events = WebhookEvent::whereIn('id', $request->validated()['event_ids'])->get();

        $total = $events->sum(fn (WebhookEvent $event) => $event->createDeliveries()->count());

        Log::info('event.replayed', [
            'events' => $events->count(),
            'deliveries' => $total,
            'bulk' => true,
        ]);

        return redirect('/events')->with(
            'status',
            "Replayed {$events->count()} ".Str::plural('event', $events->count()).
            ", creating {$total} ".Str::plural('delivery', $total).'.'
        );
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
