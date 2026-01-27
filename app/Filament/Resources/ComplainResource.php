<?php
namespace App\Filament\Resources;
use App\Filament\Resources\ComplainResource\Pages;
use App\Models\Complain;
use App\Models\Device;
use App\Models\LeadSource;
use App\Models\Service;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
class ComplainResource extends Resource
{
    protected static ?string $model = Complain::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationGroup = 'Complains & Jobs';
    protected static ?string $pluralLabel = 'Complains';
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('General Information')
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('complain_id')
                            ->label('Complain ID')
                            ->disabled() // Prevent editing
                            ->helperText('This ID will be generated automatically.') // ✅ Add info text
                            ->hint('Auto-generated.') // Optional visual hint under label
                            ->hintColor('info')
                            ->dehydrated(true),
                        Forms\Components\TextInput::make('name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mobile')
                            ->label('Customer Phone Number')
                            ->rules(['required', 'min:10'])   // <--- add this
                            ->maxLength(20)
                            ->required(),
                    ]),
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->email()
                            ->maxLength(255),
                        // ✅ Google Map field + Open Map button
                        Forms\Components\TextInput::make('google_map_location')
                            ->label('Google Map Location')
                            ->placeholder('Paste Google Maps URL here…')
                            ->maxLength(500)
                            ->reactive()
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('address')
                            ->label('Customer Address')
                            ->rows(1)
                            ->columnSpanFull(),
                    ]),
                ])
                ->columns(1),
            Forms\Components\Section::make('Complaint Details')
                ->schema([
                    Forms\Components\Select::make('lead_source_id')
                        ->label('Lead Source')
                        ->relationship('leadSource', 'lead_name')
                        ->required(),


                    Forms\Components\Select::make('product_id')
                        ->label('Devices')
                        ->multiple()
                        ->options(
                            Device::all()->mapWithKeys(
                                fn($p) => [$p->id => $p->name]
                            )
                        )->searchable()
                        ->preload()
                        ->required(),


                    Forms\Components\MultiSelect::make('service_type')
                        ->label('Service Type')
                        ->options(
                            fn() =>
                            cache()->remember(
                                'active_services',
                                60,
                                fn() =>
                                Service::where('is_active', true)
                                    ->orderBy('service_type')
                                    ->pluck('service_type', 'service_type')
                                    ->toArray()
                            )
                        )
                        ->required(),
                    Forms\Components\Select::make('first_action_code')
                        ->label('First Action Code')
                        ->options([
                            'NEW' => 'NEW',
                            'PKD' => 'Picked (PKD)',
                            'Visit' => 'Visit',
                            'RSD' => 'Reschedule Visit (RSD)',
                            'CNC' => 'Call Not Connected (CNC)',
                            'Job Cancel' => 'Job Cancel',
                        ])
                        ->default('NEW')
                        ->required()
                        ->reactive(),
                    Forms\Components\DateTimePicker::make('rsd_time')
                        ->label('Reschedule Date & Time')
                        ->visible(fn($get) => $get('first_action_code') === 'RSD'),
                    Forms\Components\Textarea::make('cancel_reason')
                        ->label('Cancel Reason')
                        ->visible(fn($get) => $get('first_action_code') === 'Job Cancel'),
                    Forms\Components\TextInput::make('estimate_repair_amount')
                        ->label('Estimate Repair Amount (₹)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01),
                    Forms\Components\TextInput::make('estimate_new_amount')
                        ->label('Estimate New Amount (₹)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01),
                    Forms\Components\Select::make('assigned_by')
                        ->label('Assigned By')
                        ->relationship('assigner', 'name')
                        ->default(fn() => Auth::id())
                        ->dehydrated()
                        ->disabled(fn() => !auth()->user()->hasAnyRole(['Administrator', 'Store Manager', 'Team Lead'])),
                    Forms\Components\MultiSelect::make('assigned_engineers')
                        ->label('Assigned Engineers')
                        ->options(User::role('Engineer')->pluck('name', 'id')->toArray())
                        ->default(fn() => [Auth::id()])
                        ->disabled(fn() => !auth()->user()->hasAnyRole(['Administrator', 'Store Manager', 'Team Lead']))
                        ->dehydrated(true)
                        ->dehydrateStateUsing(
                            fn($state) =>
                            !empty($state)
                            ? collect($state)->map(fn($id) => (int) $id)->values()->toArray()
                            : [Auth::id()] // ✅ fallback when disabled
                        ),
                ])
                ->columns(3),
        ]);
    }





    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(),
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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    // Tables\Actions\Action::make('updateFirstActionCode')
                    //     ->label('Set Action') // short label
                    //     ->icon('heroicon-o-cube-transparent') // concise editing icon
                    //     ->color('warning')
                    //     ->requiresConfirmation()
                    //     ->form([
                    //         Select::make('first_action_code')
                    //             ->label('Action Code') // shorter label
                    //             ->options([
                    //                 'NEW' => 'NEW',
                    //                 'PKD' => 'Picked (PKD)',
                    //                 'Visit' => 'Visit',
                    //                 'RSD' => 'Reschedule Visit (RSD)',
                    //                 'CNC' => 'Call Not Connected (CNC)',
                    //                 'Job Cancel' => 'Job Cancel',
                    //             ])
                    //             ->default(fn($record) => $record->first_action_code)
                    //             ->required(),
                    //     ])
                    //     ->action(function ($record, array $data) {
                    //         $record->update(['first_action_code' => $data['first_action_code']]);
                    //     })
                    //     ->visible(fn() => auth()->user()->hasAnyRole(['Administrator', 'Store Manager', 'Team Lead'])),
                    // ✅ Open Google Map Button
                    Tables\Actions\Action::make('open_map')
                        ->label('Open Map')
                        ->icon('heroicon-o-map')
                        ->color('success')
                        ->visible(fn($record) => filled($record->google_map_location))
                        ->url(
                            fn($record) =>
                            str_starts_with($record->google_map_location, 'http')
                            ? $record->google_map_location
                            : 'https://' . $record->google_map_location
                        )
                        ->openUrlInNewTab(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->dropdown()->tooltip('Actions')
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make(),
            ]);
    }
    public static function getRelations(): array
    {
        return [];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplains::route('/'),
            'create' => Pages\CreateComplain::route('/create'),
            'edit' => Pages\EditComplain::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Hide PKD records
        $query->where('first_action_code', '!=', 'PKD');

        $user = Auth::user();

        // Store Manager restriction
        if ($user && $user->hasRole('Store Manager')) {
            $query->where('store_id', $user->store_id);
        }

        // Restrict for non-admin users
        if (
            $user &&
            !$user->hasRole(['Administrator', 'Developer', 'admin']) &&
            $user->email !== 'vipprow@gmail.com'
        ) {
            $query->whereJsonContains('assigned_engineers', $user->id);
        }

        return $query;
    }

}
