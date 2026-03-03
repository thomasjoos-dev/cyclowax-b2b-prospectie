<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('shop_type')->nullable()->after('discovery_source');
            $table->boolean('has_workshop')->default(false)->after('shop_type');
            $table->boolean('has_webshop')->default(false)->after('has_workshop');
            $table->string('instagram_handle')->nullable()->after('has_webshop');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['shop_type', 'has_workshop', 'has_webshop', 'instagram_handle']);
        });
    }
};
