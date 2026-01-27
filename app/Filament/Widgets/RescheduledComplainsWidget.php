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


    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                tap(
                    Complain::query()
                        ->where('first_action_code', 'RSD'), // ✅ ONLY this

                    function ($query) {
                        $user = Auth::user();

                        // Store Manager restriction
                        if ($user && $user->hasRole('Store Manager')) {
                            $query->where('store_id', $user->store_id);
                        }

                        // Engineer restriction (same as Resource)
                        if (
                            $user &&
                            !$user->hasRole(['Administrator', 'Developer', 'admin']) &&
                            $user->email !== 'vipprow@gmail.com'
                        ) {
                            $query->whereJsonContains(
                                'assigned_engineers',
                                $user->id // 👈 SAME AS RESOURCE (int works here)
                            );
                        }
                    }
                )
                    ->orderBy('rsd_time', 'asc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('rsd_time')->label('RSD')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('complain_id')->label('Complain ID')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->label('Customer Name')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('mobile')->label('Phone')->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('first_action_code')->label('Action Code')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('assigned_engineers')
                    ->label('Assigned Engineers')
                    ->state(function ($record) {
                        if (empty($record->assigned_engineers)) {
                            return '—';
                        }

                        return User::whereIn('id', $record->assigned_engineers)
                            ->pluck('name')
                            ->implode(', ');
                    })
                    ->wrap()
                    ->sortable()->toggleable(),
                // ⭐ NEW MAP BUTTON COLUMN
                Tables\Columns\TextColumn::make('map')
                    ->label('Map')
                    ->html()
                    ->alignCenter()
                    ->state(function ($record) {
                        if (!$record->google_map_location) {
                            return '<span class="text-gray-400 text-xs">No Map</span>';
                        }
                        $url = str_starts_with($record->google_map_location, 'http')
                            ? $record->google_map_location
                            : 'https://' . $record->google_map_location;
                        return '
                <a href="' . $url . '" target="_blank"
                   class="px-2 py-1 text-xs rounded bg-green-600 text-white hover:bg-green-700" style="background:green;">
                    Open Map
                </a>
            ';
                    }),
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
            ])
            ->paginated(false);
    }
}
