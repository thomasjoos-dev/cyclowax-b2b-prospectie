<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('discovery_source', ['google_places', 'brand_locator', 'csv_import', 'manual', 'overpass'])
                ->default(null)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('discovery_source', ['google_places', 'brand_locator', 'csv_import', 'manual'])
                ->default(null)
                ->nullable()
                ->change();
        });
    }
};
