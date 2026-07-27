<?php

use App\Models\Destination;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            // Encrypted at rest via the model's `encrypted` cast; nullable so the
            // column can be added without a default, then backfilled below.
            $table->text('signing_secret')->nullable()->after('url');
        });

        // Backfill every existing destination (including soft-deleted ones) so all
        // deliveries are signable. Saving through the model applies the encrypted cast.
        Destination::withTrashed()->whereNull('signing_secret')->each(function (Destination $destination) {
            $destination->signing_secret = Destination::generateSigningSecret();
            $destination->save();
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn('signing_secret');
        });
    }
};
