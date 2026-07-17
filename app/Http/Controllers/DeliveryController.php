<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function show(Delivery $delivery): View
    {
        $delivery->load(['destination', 'event.source', 'attempts' => fn ($q) => $q->orderByDesc('attempt_number')]);

        return view('deliveries.show', ['delivery' => $delivery]);
    }
}
