<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class KnowledgeBase extends Model
{
    use HasFactory, HasRoles;
    protected $table = 'knowledge_base';
    protected $fillable = [
        'title',
        'content',
        'slug',
        'created_by',
        'status',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
