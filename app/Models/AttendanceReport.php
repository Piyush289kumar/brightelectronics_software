<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',

        'from_date',
        'to_date',

        'month',
        'year',

        'working_days',
        'present_count',
        'absent_count',
        'leave_count',
        'half_day_count',
        'late_punch_count',

        'overtime_hours',

        'pdf_file',
        'status',
        'remarks',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'overtime_hours' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
