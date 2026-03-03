<?php

namespace App\Models;

use App\Enums\BrandCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{
    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'locator_url',
    ];

    protected function casts(): array
    {
        return [
            'category' => BrandCategory::class,
        ];
    }

    /** @return BelongsToMany<Store, $this> */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class)
            ->withPivot('discovery_source', 'discovered_at')
            ->withTimestamps();
    }
}
