<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasRoleBasedDataScope
{
    /**
     * Apply role-based data visibility.
     *
     * Administrator / Developer / admin
     *     -> All records
     *
     * Manager / Store Manager / Team Leader / Team Lead
     *     -> Only user's store
     *
     * Engineer / Machine Men
     *     -> Only user's assigned records
     */
    protected static function applyRoleBasedDataScope(
        Builder $query,
        $user
    ): Builder {

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // ==========================================
        // ADMIN -> ALL DATA
        // ==========================================
        if ($user->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
        ])) {
            return $query;
        }

        // ==========================================
        // STORE MANAGEMENT -> OWN STORE
        // ==========================================
        if ($user->hasAnyRole([
            'Manager',
            'Store Manager',
            'Team Leader',
            'Team Lead',
        ])) {
            return static::applyStoreScope(
                $query,
                $user->store_id
            );
        }

        // ==========================================
        // ENGINEER / MACHINE MEN -> OWN DATA
        // ==========================================
        if ($user->hasAnyRole([
            'Engineer',
            'Machine Men',
        ])) {
            return static::applyUserScope(
                $query,
                $user->id
            );
        }

        // ==========================================
        // UNKNOWN ROLE -> NO DATA
        // ==========================================
        return $query->whereRaw('1 = 0');
    }

    /**
     * Override this in resources where store
     * is available directly.
     */
    protected static function applyStoreScope(
        Builder $query,
        ?int $storeId
    ): Builder {
        return $query->where('store_id', $storeId);
    }

    /**
     * Override this in resources where user
     * assignment is stored differently.
     */
    protected static function applyUserScope(
        Builder $query,
        int $userId
    ): Builder {
        return $query->where('user_id', $userId);
    }
}
