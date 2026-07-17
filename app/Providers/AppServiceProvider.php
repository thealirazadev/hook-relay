<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ModelsPruned::class, function (ModelsPruned $event) {
            Log::info('prune.completed', [
                'model' => $event->model,
                'count' => $event->count,
            ]);
        });

        // Per-source ingest throttle: caps a runaway provider loop from
        // overwhelming the database, scoped to each source's ingest key.
        RateLimiter::for('ingest', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->route('ingestKey'));
        });
    }
}
