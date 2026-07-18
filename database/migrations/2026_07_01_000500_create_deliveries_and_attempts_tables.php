<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('webhook_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained();
            $table->string('status');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts');
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['destination_id', 'status']);
        });

        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->smallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body_excerpt')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
        Schema::dropIfExists('deliveries');
    }
};
