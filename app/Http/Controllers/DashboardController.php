<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\WebhookEvent;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $counts = collect(Delivery::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => Delivery::where('status', $status)->count()]);

        $recentEvents = WebhookEvent::with('source')
            ->latest('received_at')
            ->limit(10)
            ->get();

        return view('dashboard', [
            'counts' => $counts,
            'deadCount' => $counts[Delivery::STATUS_DEAD],
            'recentEvents' => $recentEvents,
        ]);
    }
}
