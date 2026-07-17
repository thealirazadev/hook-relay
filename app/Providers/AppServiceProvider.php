<?php

namespace App\Providers;

use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
    }
}
