<?php

use App\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

/*
| Stateless webhook ingest route. Registered outside the "web" group in
| bootstrap/app.php, so it carries no session or CSRF middleware — the request
| signature is the only authentication.
*/

Route::post('/ingest/{ingestKey}', IngestController::class)->name('ingest');
