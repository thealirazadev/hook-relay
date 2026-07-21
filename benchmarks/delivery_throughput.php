<?php

/*
| hook-relay delivery benchmark
|
| Measures real single-worker delivery throughput against a local echo server
| and confirms the retry backoff schedule + jitter bounds empirically. No
| mocking: each delivery is a real Guzzle POST over loopback plus the real DB
| writes (status transition + attempt row) the production job performs.
|
| Usage:  php benchmarks/delivery_throughput.php [count]   (default 500)
|
| It runs against a throwaway SQLite file and a php -S echo server, both torn
| down on exit; it never touches your dev database.
*/

use App\Jobs\DeliverEvent;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use App\Models\WebhookEvent;
use App\Support\Backoff;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$count = max(1, (int) ($argv[1] ?? 500));
$warmup = min(20, (int) floor($count / 10));
$port = 8799;
$dbPath = sys_get_temp_dir().'/hook_relay_bench_'.getmypid().'.sqlite';
$routerPath = sys_get_temp_dir().'/hook_relay_bench_router_'.getmypid().'.php';

// Throwaway SQLite + synchronous queue so the delivery job runs inline.
touch($dbPath);
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $dbPath,
    'queue.default' => 'sync',
]);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true, '--database' => 'sqlite']);

// Minimal echo server: 200 OK, empty body, no logic.
file_put_contents($routerPath, "<?php http_response_code(200); echo 'ok';\n");
$echo = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", $routerPath],
    [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);

$cleanup = function () use (&$echo, $dbPath, $routerPath) {
    if (is_resource($echo)) {
        proc_terminate($echo);
        proc_close($echo);
    }
    @unlink($dbPath);
    @unlink($routerPath);
};
register_shutdown_function($cleanup);

// Wait for the echo server to accept connections.
$url = "http://127.0.0.1:{$port}/hook";
$ready = false;
for ($i = 0; $i < 100; $i++) {
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if ($fp) {
        fclose($fp);
        $ready = true;
        break;
    }
    usleep(50_000);
}
if (! $ready) {
    fwrite(STDERR, "echo server did not start on port {$port}\n");
    exit(1);
}

// Seed one source -> one destination (the echo server).
$source = Source::create([
    'name' => 'bench',
    'provider' => 'generic',
    'signing_secret' => 'bench-secret',
    'active' => true,
]);
$destination = Destination::create(['name' => 'echo', 'url' => $url, 'active' => true]);
$source->destinations()->attach($destination->id);

// Build pending deliveries up front so timing covers only the delivery work.
$makeDelivery = function (int $n) use ($source, $destination): Delivery {
    $event = WebhookEvent::create([
        'source_id' => $source->id,
        'provider_event_id' => "bench-{$n}",
        'dedupe_key' => "bench-{$n}",
        'event_type' => 'bench.event',
        'headers' => ['content-type' => 'application/json'],
        'payload' => json_encode(['n' => $n, 'pad' => str_repeat('x', 256)]),
        'content_type' => 'application/json',
        'received_at' => now(),
    ]);

    return Delivery::create([
        'webhook_event_id' => $event->id,
        'destination_id' => $destination->id,
        'status' => Delivery::STATUS_PENDING,
        'attempt_count' => 0,
        'max_attempts' => 8,
    ]);
};

$deliveries = [];
for ($n = 0; $n < $count + $warmup; $n++) {
    $deliveries[] = $makeDelivery($n);
}

// Warm up (JIT of the HTTP client, connection setup) without counting it.
for ($i = 0; $i < $warmup; $i++) {
    dispatch_sync(new DeliverEvent($deliveries[$i]));
}

$start = hrtime(true);
for ($i = $warmup; $i < $warmup + $count; $i++) {
    dispatch_sync(new DeliverEvent($deliveries[$i]));
}
$elapsedSec = (hrtime(true) - $start) / 1e9;

$delivered = Delivery::where('status', Delivery::STATUS_DELIVERED)->count();
$throughput = $count / $elapsedSec;

// Per-attempt latency (excludes warmup by id ordering).
$durations = DB::table('delivery_attempts')->orderBy('created_at')->pluck('duration_ms')->all();
$durations = array_slice($durations, $warmup);
sort($durations);
$pct = fn (array $a, float $p) => $a === [] ? 0 : $a[min(count($a) - 1, (int) floor($p * (count($a) - 1)))];
$mean = $durations === [] ? 0 : array_sum($durations) / count($durations);

// Backoff schedule + empirical jitter bounds (10k samples per attempt).
$backoff = new Backoff;
$schedule = [];
for ($attempt = 1; $attempt <= 7; $attempt++) {
    $base = $backoff->base($attempt);
    $min = PHP_INT_MAX;
    $max = 0;
    for ($s = 0; $s < 10_000; $s++) {
        $d = $backoff->delay($attempt);
        $min = min($min, $d);
        $max = max($max, $d);
    }
    $schedule[] = [$attempt, $base, $min, $max];
}

$fmt = fn (float $s) => rtrim(rtrim(number_format($s, 2), '0'), '.');

echo "\n=== hook-relay delivery benchmark ===\n";
echo "deliveries timed : {$count} (plus {$warmup} warmup)\n";
echo 'wall time        : '.$fmt($elapsedSec)." s\n";
echo 'throughput       : '.$fmt($throughput)." deliveries/s (single synchronous worker)\n";
echo "delivered/total  : {$delivered}/".($count + $warmup)."\n";
echo 'attempt latency  : mean '.$fmt($mean).' ms | p50 '.$pct($durations, 0.50).' ms | p95 '.$pct($durations, 0.95)." ms\n";

echo "\nbackoff schedule (seconds; jitter x[0.8,1.2] over 10k samples):\n";
echo "  attempt  base   observed-min  observed-max\n";
foreach ($schedule as [$attempt, $base, $min, $max]) {
    printf("  %-7d  %-5d  %-12d  %-12d\n", $attempt, $base, $min, $max);
}

echo "\n--- markdown ---\n";
echo "| Metric | Value |\n|---|---|\n";
echo "| Deliveries timed | {$count} |\n";
echo '| Throughput | '.$fmt($throughput)." deliveries/s (single worker) |\n";
echo '| Wall time | '.$fmt($elapsedSec)." s |\n";
echo '| Attempt latency (mean / p50 / p95) | '.$fmt($mean).' / '.$pct($durations, 0.50).' / '.$pct($durations, 0.95)." ms |\n";
echo "\n";
