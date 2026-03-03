<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_store', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('discovery_source')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_store');
    }
};
