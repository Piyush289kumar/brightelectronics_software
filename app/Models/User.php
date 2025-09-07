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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
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

}
