<?php

namespace App\Models;

use App\Enums\BrandCategory;
use App\Enums\DiscoverySource;
use App\Enums\PipelineStatus;
use App\Enums\ShopType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'country',
        'postal_code',
        'phone',
        'email',
        'website',
        'latitude',
        'longitude',
        'pipeline_status',
        'is_existing_customer',
        'discovery_source',
        'shop_type',
        'has_workshop',
        'has_webshop',
        'instagram_handle',
        'notes',
        'last_contacted_at',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_existing_customer' => 'boolean',
            'has_workshop' => 'boolean',
            'has_webshop' => 'boolean',
            'last_contacted_at' => 'datetime',
            'pipeline_status' => PipelineStatus::class,
            'discovery_source' => DiscoverySource::class,
            'shop_type' => ShopType::class,
        ];
    }

    /** @return BelongsToMany<Brand, $this> */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class)
            ->withPivot('discovery_source', 'discovered_at')
            ->withTimestamps();
    }

    public function premiumBikeCount(): int
    {
        return $this->brands()
            ->whereIn('category', collect(BrandCategory::bikeCategories())->map->value)
            ->count();
    }

    public function accessoryBrandCount(): int
    {
        return $this->brands()
            ->whereIn('category', collect(BrandCategory::accessoryCategories())->map->value)
            ->count();
    }

    public function isQualifiedLead(): bool
    {
        return $this->premiumBikeCount() >= 1 && $this->has_workshop;
    }
}
