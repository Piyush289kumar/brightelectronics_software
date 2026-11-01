<?php

namespace App\Filament\Widgets;

use App\Models\Complain;
use App\Models\User;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RescheduledComplainsWidget extends BaseWidget
{
    protected static ?string $heading = 'Rescheduled Complaints (RSD)';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
            'Manager',
            'Store Manager',
            'Team Leader',
            'Team Lead',
            'Engineer',
            'Machine Men',
        ]) ?? false;
    }

    public function table(Tables\Table $table): Tables\Table
    {
        $user = Auth::user();

        $query = Complain::query()
            ->where('first_action_code', 'RSD');

        $isAdmin = $user?->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
        ]);

        $isEngineer = $user?->hasAnyRole([
            'Engineer',
            'Machine Men',
        ]);

        $isBranchManagement = $user?->hasAnyRole([
            'Manager',
            'Store Manager',
            'Team Leader',
            'Team Lead',
        ]);

        if (! $isAdmin) {
            $storeIds = $user?->multi_store_id ?? [];

            $storeIds = is_array($storeIds)
                ? array_map('intval', $storeIds)
                : [];

            if (empty($storeIds)) {
                $query->whereRaw('1 = 0');
            } elseif ($isBranchManagement || $isEngineer) {
                $query->whereIn('store_id', $storeIds);

                if ($isEngineer) {
                    $userId = (int) $user->id;

                    $query->where(function ($q) use ($userId) {
                        $q->whereJsonContains('assigned_engineers', $userId)
                            ->orWhereJsonContains(
                                'assigned_engineers',
                                (string) $userId
                            );
                    });
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $table
            ->query(
                $query->orderBy('rsd_time', 'asc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('rsd_time')
                    ->label('RSD')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('complain_id')
                    ->label('Complain ID')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Customer Name')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mobile')
                    ->label('Phone')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('first_action_code')
                    ->label('Action Code')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assigned_engineers')
                    ->label('Assigned Engineers')
                    ->state(function ($record) {
                        $engineerIds = $record->assigned_engineers ?? [];

                        if (empty($engineerIds)) {
                            return '—';
                        }

                        return User::whereIn('id', $engineerIds)
                            ->pluck('name')
                            ->implode(', ');
                    })
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('map')
                    ->label('Map')
                    ->html()
                    ->alignCenter()
                    ->state(function ($record) {
                        if (! $record->google_map_location) {
                            return '<span class="text-gray-400 text-xs">No Map</span>';
                        }

                        $url = $record->google_map_location;

                        if (
                            ! str_starts_with($url, 'https://') &&
                            ! str_starts_with($url, 'http://')
                        ) {
                            $url = 'https://' . $url;
                        }

                        $safeUrl = e($url);

                        return '
                            <a href="' . $safeUrl . '"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="px-2 py-1 text-xs rounded text-white"
                               style="background:green;">
                                Open Map
                            </a>
                        ';
                    }),
            ])
            ->actions([])
            ->paginated(false);
    }
}
