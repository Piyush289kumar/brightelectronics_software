<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class SiteInventoryIssue extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'site_id',
        'issued_by',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }


    public function items()
    {
        return $this->hasMany(SiteInventoryIssueItem::class);
    }
}
