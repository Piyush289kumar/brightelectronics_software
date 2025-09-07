<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;

class Document extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = [
        'ref',
        'model_type',
        'model_id',
        'document_template_id',
        'body',
        'is_send',
    ];

    /**
     * A document belongs to a template
     */
    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    /**
     * A document can be linked to an invoice (one-to-one)
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'document_id');
    }

    /**
     * Polymorphic relation (if you want to use model_type/model_id)
     */
    public function model()
    {
        return $this->morphTo();
    }
}
