<?php

namespace App\Models;

use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use \TomatoPHP\FilamentLanguageSwitcher\Traits\InteractsWithLanguages;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, InteractsWithLanguages;

    protected $fillable = [
        'name',
        'email',
        'password',
        'store_id',
        'multi_store_id',
        'basic_salary',
        'incentive',
        'allowance',
        'user_code',
        'joining_date'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'basic_salary' => 'decimal:2',
            'multi_store_id' => 'array',
            'incentive' => 'decimal:2',
            'joining_date' => 'date',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // Get all stores assigned through multi_store_id
    public function getMultiStoresAttribute()
    {
        $storeIds = $this->multi_store_id ?? [];

        if (! is_array($storeIds) || empty($storeIds)) {
            return collect();
        }

        return Store::whereIn('id', $storeIds)->get();
    }

    // REMOVE these to avoid shadowing Spatie methods:
    // public function hasRole(string $role): bool { ... }
    // public function isStoreManager(): bool { ... }
    // public function isAdmin(): bool { ... }

    // If you want convenience accessors, delegate to the trait:
    public function isStoreManager(): bool
    {
        return $this->hasRole('Manager'); // uses Spatie's hasRole
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(['Administrator', 'Developer', 'admin']);
    }

    /**
     * Get all store IDs accessible to this user.
     */
    public function accessibleStoreIds(): array
    {
        return collect($this->multi_store_id ?? [])
            ->merge([$this->store_id])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
