<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
class UserTarget extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = [
        'store_target_id',
        'user_id',
        'assigned_amount',
        'remaining_amount',
        'achieved_amount',
    ];
    protected $casts = [
        'assigned_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
    ];
    public function storeTarget()
    {
        return $this->belongsTo(StoreTarget::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // Auto-update parent StoreTarget amount on change
    protected static function booted()
    {
        static::saved(function ($userTarget) {
            $userTarget->updateParentTotal();
        });
        static::deleted(function ($userTarget) {
            $userTarget->updateParentTotal();
        });
    }
    public function updateParentTotal()
    {
        $storeTarget = $this->storeTarget;
        if (!$storeTarget) {
            return;
        }

        // Calculate totals
        $totalAssigned = $storeTarget->userTargets()->sum('assigned_amount');
        $totalCollected = $storeTarget->userTargets()->sum('achieved_amount');

        // Update the store_target table
        $storeTarget->update([
            'amount' => round($totalAssigned, 2),
            'collected_amount' => round($totalCollected, 2),
        ]);
    }
}
