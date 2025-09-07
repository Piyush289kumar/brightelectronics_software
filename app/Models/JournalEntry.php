<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class JournalEntry extends Model
{
    use HasFactory, HasRoles, SoftDeletes;
    protected $fillable = ['reference', 'date', 'description', 'journalable_id', 'journalable_type', 'created_by'];

    public function postings()
    {
        return $this->hasMany(JournalPosting::class);
    }

    public function journalable()
    {
        return $this->morphTo();
    }
}
