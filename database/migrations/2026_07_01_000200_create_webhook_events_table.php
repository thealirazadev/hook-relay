<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('provider_event_id')->nullable();
            $table->string('dedupe_key', 191);
            $table->string('event_type')->nullable();
            $table->json('headers');
            $table->longText('payload');
            $table->string('content_type')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['source_id', 'dedupe_key']);
            $table->index(['source_id', 'received_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
