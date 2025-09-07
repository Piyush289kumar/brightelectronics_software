<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Brand extends Model
{
    use HasFactory, SoftDeletes, HasRoles;
    protected $fillable = [
        'name',
        'slug',
        'owner_name',
        'contact_number',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'gst_number',
        'pan_number',
        'logo_path',
        'description',
        'is_active',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
