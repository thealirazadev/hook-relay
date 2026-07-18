<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DlqController extends Controller
{
    public function index(): View
    {
        $deliveries = Delivery::where('status', Delivery::STATUS_DEAD)
            ->with(['destination', 'event.source'])
            ->latest('updated_at')
            ->paginate(25);

        return view('dlq.index', ['deliveries' => $deliveries]);
    }

    public function requeue(Delivery $delivery): RedirectResponse
    {
        if ($delivery->status !== Delivery::STATUS_DEAD) {
            return redirect('/dlq')->with('error', 'Only dead deliveries can be requeued.');
        }

        $delivery->requeue();

        return redirect('/dlq')->with('status', 'Requeued 1 delivery.');
    }

    public function requeueAll(): RedirectResponse
    {
        $count = 0;

        Delivery::where('status', Delivery::STATUS_DEAD)
            ->chunkById(100, function ($deliveries) use (&$count) {
                foreach ($deliveries as $delivery) {
                    $delivery->requeue();
                    $count++;
                }
            });

        return redirect('/dlq')->with('status', "Requeued {$count} deliveries.");
    }
}
