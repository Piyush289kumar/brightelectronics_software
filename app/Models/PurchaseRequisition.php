<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;

class PurchaseRequisition extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'requested_by',
        'reference',
        'notes',
        'status',
        'requisition_pdf',
        'priority',
        'approved_by',
        'approved_at',
        'meta',        
    ];

    protected $casts = [
        'meta' => 'array',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionItem::class)
            ->whereNotNull('product_id'); // only save real items
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->requested_by)) {
                $model->requested_by = Auth::id();
            }
        });
    }

}
