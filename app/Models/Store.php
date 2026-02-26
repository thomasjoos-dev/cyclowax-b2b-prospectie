<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_existing_customer' => 'boolean',
        ];
    }

    /** @var array<string, string> */
    public static array $statusLabels = [
        'niet_gecontacteerd' => 'Niet gecontacteerd',
        'gecontacteerd' => 'Gecontacteerd',
        'in_gesprek' => 'In gesprek',
        'partner' => 'Partner',
        'afgewezen' => 'Afgewezen',
    ];

    /** @var array<string, string> */
    public static array $statusColors = [
        'niet_gecontacteerd' => 'gray',
        'gecontacteerd' => 'blue',
        'in_gesprek' => 'yellow',
        'partner' => 'green',
        'afgewezen' => 'red',
    ];
}
