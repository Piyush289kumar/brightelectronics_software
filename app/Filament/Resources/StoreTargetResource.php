<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreTargetResource\Pages;
use App\Filament\Traits\HasRoleBasedAccess;
use App\Models\Store;
use App\Models\StoreTarget;
use App\Services\TargetDistributor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Get;

class StoreTargetResource extends BaseResource
{
    use HasRoleBasedAccess;
    protected static ?string $model = StoreTarget::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $label = 'Branch Targets';
    protected static ?string $navigationGroup = 'Targets';
    protected static function permission(string $action): string
    {
        return "{$action}_store::target";
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('store_id')
                ->relationship(
                    name: 'store',
                    titleAttribute: 'code',
                    modifyQueryUsing: function (Builder $query) {
                        $user = Auth::user();
                        if (! $user) {
                            return $query->whereRaw('1 = 0');
                        }
                        if ($user->hasAnyRole(['Administrator', 'Developer', 'admin'])) {
                            return $query;
                        }
                        $storeIds = $user->multi_store_id ?? [];

                        return $query->whereIn('id', is_array($storeIds) ? $storeIds : []);
                    }
                )
                ->preload()
                ->required()
                ->label('Branch')
                ->default(fn() => collect(Auth::user()?->multi_store_id ?? [])->first())
                ->live()
                ->afterStateUpdated(fn(callable $set) => $set('assigned_engineers', []))
                ->dehydrated(true)
                ->options(
                    fn() => Store::query()
                        ->get()
                        ->mapWithKeys(fn($store) => [
                            $store->id => "{$store->name} ({$store->code})",
                        ])
                        ->toArray()
                ),


            TextInput::make('year')
                ->label('Year')
                ->numeric()
                ->default(now()->year)
                ->minValue(2020)
                ->maxValue(now()->year + 1)
                ->required(),

            Select::make('month')
                ->label('Month')
                ->options([
                    1 => 'Jan',
                    2 => 'Feb',
                    3 => 'Mar',
                    4 => 'Apr',
                    5 => 'May',
                    6 => 'Jun',
                    7 => 'Jul',
                    8 => 'Aug',
                    9 => 'Sep',
                    10 => 'Oct',
                    11 => 'Nov',
                    12 => 'Dec',
                ])
                ->default(now()->month)
                ->required()
                ->disableOptionWhen(function ($value, Get $get) {

                    $storeId = $get('store_id');
                    $year = $get('year');

                    if (!$storeId || !$year)
                        return false;

                    return StoreTarget::where('store_id', $storeId)
                        ->where('year', $year)
                        ->where('month', $value)
                        ->exists();
                })
                ->helperText('Already created months are disabled'),

            TextInput::make('amount')
                ->label('Target Amount (₹)')
                ->numeric()
                ->minValue(0.01)
                ->required(),

            Toggle::make('include_previous')
                ->label('Include Previous Remaining')
                ->helperText('If enabled, remaining targets from previous months will be added to this target.')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('store.name')->label('Branch')->formatStateUsing(fn($state, $record) => $record->store ? "{$record->store->name} ({$record->store->code})" : '-')->searchable(['store.name', 'store.code'])->sortable()->toggleable(),
            TextColumn::make('year')->sortable(),
            TextColumn::make('month')
                ->label('Month')
                ->formatStateUsing(fn($state) => \Carbon\Carbon::create()->month($state)->format('F'))
                ->sortable(),
            TextColumn::make('amount')->label('Target (₹)')->money('INR'),
            TextColumn::make('team_lead_target')
                ->label('Team Target')
                ->state(fn($record) => $record->amount)
                ->money('INR'),

            TextColumn::make('manager_target')
                ->label('Manager Target')
                ->state(fn($record) => $record->amount + 30000)
                ->money('INR'),
            TextColumn::make('previous_remaining_sum')->label('Prev. Remaining (₹)')->money('INR'),
            TextColumn::make('userTargets_sum_assigned_amount')
                ->label('Distributed Total (₹)')
                ->state(fn($record) => $record->userTargets()->sum('assigned_amount'))
                ->money('INR')
                ->sortable(),
            Tables\Columns\TextColumn::make('collected_amount')
                ->label('Total Collection (₹)')
                ->money('INR')
                ->sortable(),
            Tables\Columns\TextColumn::make('remaining_target')
                ->label('Remaining (₹)')
                ->state(fn($record) => max($record->amount - $record->collected_amount, 0))
                ->money('INR')
                ->sortable(),

            IconColumn::make('distributed')->label('Distributed')->boolean(),
            TextColumn::make('created_at')->label('Created')->dateTime(),
        ])
            ->filters([
                Tables\Filters\Filter::make('current_month')
                    ->label('Current Month')
                    ->default() // ✅ auto apply
                    ->query(function ($query) {
                        return $query
                            ->where('month', now()->month)
                            ->where('year', now()->year);
                    }),

                Tables\Filters\SelectFilter::make('month')
                    ->label('Month')
                    ->options([
                        1 => 'Jan',
                        2 => 'Feb',
                        3 => 'Mar',
                        4 => 'Apr',
                        5 => 'May',
                        6 => 'Jun',
                        7 => 'Jul',
                        8 => 'Aug',
                        9 => 'Sep',
                        10 => 'Oct',
                        11 => 'Nov',
                        12 => 'Dec',
                    ])
                    ->query(
                        fn($query, $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $month) =>
                            $q->where('month', $month)
                        )
                    ),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Year')
                    ->options(
                        collect(range(now()->year - 5, now()->year + 1))
                            ->mapWithKeys(fn($y) => [$y => $y])
                    )
                    ->query(
                        fn($query, $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $year) =>
                            $q->where('year', $year)
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('distribute')
                    ->label('Distribute Target')
                    ->icon('heroicon-o-chart-bar')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function (StoreTarget $record) {
                        if (!$record->distributed) {
                            TargetDistributor::createAndDistribute(
                                $record->store,
                                $record->year,
                                $record->month,
                                (float) $record->amount,
                                (bool) $record->include_previous,
                                Auth::user()
                            );
                        }
                    })->visible(
                        fn(StoreTarget $record) =>
                        !$record->distributed &&
                            auth()->user()->hasRole([
                                'Administrator',
                                'Developer',
                                'admin',
                                'Store Manager',
                                'Team Leader',
                                'Team Lead'
                            ])
                    ),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreTargets::route('/'),
            // 'create' => Pages\CreateStoreTarget::route('/create'),
            // 'edit' => Pages\EditStoreTarget::route('/{record}/edit'),
        ];
    }
}
