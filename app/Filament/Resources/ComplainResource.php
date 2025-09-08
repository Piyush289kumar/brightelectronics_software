<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplainResource\Pages;
use App\Models\Complain;
use App\Models\LeadSource;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ComplainResource extends Resource
{
    protected static ?string $model = Complain::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationGroup = 'Complains & Jobs';
    protected static ?string $pluralLabel = 'Complains';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([

                        Grid::make(4)->schema([
                            Forms\Components\TextInput::make('complain_id')
                                ->label('Complain ID')
                                ->disabled()
                                ->required(),

                            Forms\Components\TextInput::make('name')
                                ->label('Customer Name')
                                ->required()
                                ->maxLength(255)
                                ->reactive()
                                ->afterStateUpdated(fn($state, callable $set) => $set('complain_id', self::generateComplainId($state))),

                            Forms\Components\TextInput::make('mobile')
                                ->label('Customer Phone Number')
                                ->maxLength(20)
                                ->reactive()
                                ->required()
                                ->afterStateUpdated(fn($state, callable $set, $get) => $set('complain_id', self::generateComplainId($get('name'), $state))),

                            Forms\Components\TextInput::make('customer_email')
                                ->label('Customer Email')
                                ->email()
                                ->maxLength(255),
                        ]),


                        Forms\Components\Textarea::make('address')
                            ->label('Customer Address'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Complaint Details')
                    ->schema([
                        Forms\Components\Select::make('lead_source_id')
                            ->label('Lead Source')
                            ->relationship('leadSource', 'lead_name')
                            ->required(),


                        Forms\Components\MultiSelect::make('service_type')
                            ->label('Service Type')
                            ->options(function () {
                                return cache()->remember('active_services', 60, function () {
                                    return Service::where('is_active', true)
                                        ->orderBy('service_type')
                                        ->pluck('service_type', 'service_type')
                                        ->toArray();
                                });
                            })
                            ->required(),

                        Forms\Components\MultiSelect::make('product_id')
                            ->label('Products')
                            ->options(Product::all()->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->hint('Select one or more products'),

                        Forms\Components\Select::make('size')
                            ->label('Screen Sizes')
                            ->options([
                                '24' => '24 inch',
                                '32' => '32 inch',
                                '42' => '42 inch',
                                '50' => '50 inch',
                                '55' => '55 inch',
                                '65' => '65 inch',
                                '85' => '85 inch',
                            ])
                            ->hint('Select applicable screen sizes'),

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
                            ->label('Estimate Repair Amount')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),

                        Forms\Components\TextInput::make('estimate_new_amount')
                            ->label('Estimate New Amount')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),


                        Forms\Components\Select::make('assigned_by')
                            ->label('Assigned By')
                            ->relationship('assigner', 'name')
                            ->dehydrated(true)
                            ->default(fn() => Auth::id()) // Default to logged-in user ID
                            ->disabled(fn() => !auth()->user()->isAdmin() && !auth()->user()->isStoreManager()),

                        Forms\Components\MultiSelect::make('assigned_engineers')
                            ->label('Assigned Engineers')
                            ->dehydrated(true)
                            ->options(function () {
                                return User::role('Engineer')->pluck('name', 'id')->toArray();
                            })
                            ->disabled(fn() => !auth()->user()->isAdmin() && !auth()->user()->isStoreManager()),


                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Pending' => 'Pending',
                                'In Progress' => 'In Progress',
                                'Completed' => 'Completed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Pending'),
                    ])
                    ->columns(3),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('complain_id')->label('Complain ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Customer Name')->sortable(),
                Tables\Columns\TextColumn::make('mobile')->label('Phone')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->sortable(),
                Tables\Columns\TextColumn::make('first_action_code')->label('Action Code')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'Pending' => 'Pending',
                    'In Progress' => 'In Progress',
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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

    public static function generateComplainId($name, $mobile = null): string
    {
        $datePart = now()->format('md');
        $namePart = Str::of($name)->trim()->upper()->split('/\s+/')->map(fn($part) => $part)->flatten();

        if ($namePart->count() >= 2) {
            $letters = substr($namePart->first(), 0, 1) . substr($namePart->last(), 0, 1);
        } else {
            $letters = substr($namePart->first(), 0, 2);
        }

        $phonePart = substr($mobile ?? '0000000000', -4);

        return "{$datePart}{$letters}{$phonePart}";
    }
}
