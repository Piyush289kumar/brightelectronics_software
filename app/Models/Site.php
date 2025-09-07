<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Site extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'location',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'phone',
        'email',
        'status',
        'opening_time',
        'closing_time',
        'settings',
        'notes',
    ];

    protected $casts = [
        'settings' => 'array',
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function inventories()
    {
        return $this->hasMany(StoreInventory::class);
    }
}
