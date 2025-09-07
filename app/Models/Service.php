<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Service extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'service_type',
        'category',
        'condition',
        'price',
        'duration',
        'priority',
        'is_active',
        'tags',
        'meta',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tags' => 'array',
        'meta' => 'array',
    ];
}
