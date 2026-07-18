<?php

namespace App\Filament\Traits;

trait HasRoleBasedAccess
{
    protected static function allowedRoles(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        $roles = static::allowedRoles();

        if (empty($roles)) {
            return true;
        }

        return auth()->check()
            && auth()->user()->hasAnyRole($roles);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}