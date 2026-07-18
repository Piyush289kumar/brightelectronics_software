<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class StoreResource extends Resource
{    
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Branches';
    protected static ?string $label = 'Branch';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 1000)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {

                                $date = $get('opening_date');

                                if (!$state || !$date) {
                                    return;
                                }

                                $date = \Carbon\Carbon::parse($date);

                                $name = preg_replace('/[^A-Za-z0-9]/', '', $state);

                                $id = $get('id') ?? (\App\Models\Store::max('id') + 1);

                                $set(
                                    'code',
                                    $name . $id . $date->format('my')
                                );
                            }),
                        Forms\Components\DatePicker::make('opening_date')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {

                                $name = $get('name');

                                if (!$name || !$state) {
                                    return;
                                }

                                $date = \Carbon\Carbon::parse($state);

                                $name = preg_replace('/[^A-Za-z0-9]/', '', $name);

                                $id = $get('id') ?? (\App\Models\Store::max('id') + 1);

                                $set(
                                    'code',
                                    $name . $id . $date->format('my')
                                );
                            }),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->readOnly()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('location')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('latitude'),
                        Forms\Components\TextInput::make('longitude'),

                        Forms\Components\TextInput::make('google_map_location')
                            ->label('Google Map Location')
                            ->readOnly()
                            ->live()
                            ->required(
                                fn(callable $get) =>
                                in_array($get('first_action_code'), ['PKD', 'Visit'])
                            )
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('fetchLocation')
                                    ->icon('heroicon-o-map-pin')
                                    ->alpineClickHandler(<<<'JS'
                                    navigator.geolocation.getCurrentPosition(
                                        (position) => {
                                            const lat = position.coords.latitude;
                                            const lng = position.coords.longitude;
                                            const url = `https://www.google.com/maps?q=${lat},${lng}`;

                                            $wire.set('data.latitude', lat);
                                            $wire.set('data.longitude', lng);
                                            $wire.set('data.google_map_location', url);

                                            window.dispatchEvent(new CustomEvent('complain-map-updated', {
                                                detail: { lat, lng }
                                            }));
                                        },
                                        (error) => {
                                            alert(error.message);
                                        },
                                        {
                                            enableHighAccuracy: true,
                                            timeout: 15000,
                                            maximumAge: 0
                                        }
                                    );
                                    JS)
                            ),

                        Forms\Components\ViewField::make('map_picker')
                            ->view('filament.components.map-picker')
                            ->columnSpanFull(),
                    ])->columns(4),

                Forms\Components\Section::make('Address Details')
                    ->schema([
                        Forms\Components\TextInput::make('address')->maxLength(255),
                        Forms\Components\TextInput::make('city')->maxLength(100),
                        Forms\Components\TextInput::make('state')->maxLength(100),
                        Forms\Components\TextInput::make('pincode')->maxLength(6),
                        Forms\Components\TextInput::make('country')->default('India')->maxLength(100),
                    ])->columns(3),

                Forms\Components\Section::make('Branch Infrastructure Details')
                    ->schema([

                        Forms\Components\FileUpload::make('rent_agreement')
                            ->label('Rent Agreement')
                            ->disk('public')
                            ->directory('branch-documents/rent-agreement')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->downloadable()
                            ->openable(),

                        Forms\Components\FileUpload::make('gumasta_license')
                            ->label('Gumasta Certificate')
                            ->disk('public')
                            ->directory('branch-documents/gumasta')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->downloadable()
                            ->openable(),

                        Forms\Components\FileUpload::make('trade_license')
                            ->label('Trade / Shop License')
                            ->disk('public')
                            ->directory('branch-documents/trade-license')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->downloadable()
                            ->openable(),

                        Forms\Components\TextInput::make('ivrs_number')
                            ->label('IVRS Number'),

                        Forms\Components\TextInput::make('shutter_lock_number')
                            ->label('Shutter Lock Number 🔒'),

                        Forms\Components\Select::make('internet_provider')
                            ->options([
                                'Airtel' => 'Airtel',
                                'Jio Fiber' => 'Jio Fiber',
                                'BSNL' => 'BSNL',
                                'ACT' => 'ACT',
                                'Other' => 'Other',
                            ])
                            ->searchable(),

                        Forms\Components\TextInput::make('router_ip')
                            ->label('Router IP'),

                        Forms\Components\TextInput::make('router_username'),

                        Forms\Components\TextInput::make('router_password')
                            ->password()
                            ->revealable(),

                        Forms\Components\TextInput::make('dvr_nvr_ip')
                            ->label('DVR/NVR IP'),

                        Forms\Components\TextInput::make('dvr_nvr_username'),

                        Forms\Components\TextInput::make('dvr_nvr_password')
                            ->password()
                            ->revealable(),

                    ])
                    ->columns(3)
                    ->collapsible(),


                Forms\Components\Section::make('Account Details')
                    ->schema([
                        // Banking/Account Details
                        Forms\Components\TextInput::make('account_holder_name')
                            ->label('Account Holder Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_number')
                            ->label('Account Number')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('ifsc_code')
                            ->label('IFSC Code')
                            ->maxLength(20),
                        Forms\Components\Select::make('account_type')
                            ->label('Account Type')
                            ->options([
                                'savings' => 'Savings',
                                'current' => 'Current',
                            ]),
                        Forms\Components\TextInput::make('branch_name')
                            ->label('Branch Name')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('GST & Tax')
                    ->schema([
                        Forms\Components\TextInput::make('gst_number')->maxLength(15),
                        Forms\Components\TextInput::make('pan_number')->maxLength(10),
                        Forms\Components\TextInput::make('default_tax_rate')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00),
                    ])->columns(3),

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('phone')->maxLength(15),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    ])->columns(2),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\Textarea::make('settings')
                    ->json() // Filament supports JSON editing from v3+
                    ->nullable()
                    ->helperText('JSON for store-specific settings'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->formatStateUsing(fn($state) => 'BRT-' . str_pad($state, 4, '0', STR_PAD_LEFT))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->sortable(),
                Tables\Columns\TextColumn::make('city')->sortable(),
                Tables\Columns\TextColumn::make('state')->sortable(),
                Tables\Columns\TextColumn::make('gst_number'),
                Tables\Columns\TextColumn::make('phone'),

                Tables\Columns\TextColumn::make('ivrs_number')
                    ->label('IVRS'),

                Tables\Columns\TextColumn::make('internet_provider')
                    ->label('Internet'),

                Tables\Columns\TextColumn::make('shutter_lock_number')
                    ->label('Lock No.')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('rent_agreement')
                    ->label('Rent')
                    ->boolean()
                    ->getStateUsing(fn($record) => filled($record->rent_agreement)),

                Tables\Columns\IconColumn::make('gumasta_license')
                    ->label('Gumasta')
                    ->boolean()
                    ->getStateUsing(fn($record) => filled($record->gumasta_license)),

                Tables\Columns\IconColumn::make('trade_license')
                    ->label('License')
                    ->boolean()
                    ->getStateUsing(fn($record) => filled($record->trade_license)),
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('status')
                    ->query(fn($query) => $query->where('status', 'active'))
                    ->label('Active Stores'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
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
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->dropdown()->tooltip('Actions')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }

    /**
     * Restrict floors listing to manager's store.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && $user->isStoreManager()) {
            // Show only the store assigned to the manager
            $query->where('id', $user->store_id);
        }

        return $query;
    }
}
