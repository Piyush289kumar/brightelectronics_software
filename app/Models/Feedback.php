<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Feedback extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'order_id',
        'customer_id',
        'manager_id',
        'rating',
        'comment',
        'type',
        'response_by',
        'response',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'response_by');
    }
}
