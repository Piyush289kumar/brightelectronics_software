<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

abstract class BaseResource extends Resource
{
    protected static function permission(string $action): string
    {
        $model = Str::snake(class_basename(static::getModel()));

        return "{$action}_{$model}";
    }

    // Permissions Start
    public static function canViewAny(): bool
    {
        return Gate::allows(static::permission('view_any'));
    }

    public static function canCreate(): bool
    {
        return Gate::allows(static::permission('create'));
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows(static::permission('update'));
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows(static::permission('delete'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
    // Permissions End
}
