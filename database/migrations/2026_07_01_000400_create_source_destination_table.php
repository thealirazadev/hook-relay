<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_destination', function (Blueprint $table) {
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();

            $table->unique(['source_id', 'destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_destination');
    }
};
