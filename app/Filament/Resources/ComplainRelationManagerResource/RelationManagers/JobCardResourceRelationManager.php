<?php

namespace App\Filament\Resources\ComplainRelationManagerResource\RelationManagers;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class JobCardResourceRelationManager extends RelationManager
{
    // Link to the 'complain' relation in JobCard model
    protected static string $relationship = 'complain';

    protected static ?string $recordTitleAttribute = 'complain_id';

    // Form for editing complain data (editable only for admin/store manager)
    public function form(Form $form): Form
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
                                ->afterStateUpdated(fn($state, callable $set) => $set('complain_id', \App\Filament\Resources\ComplainResource::generateComplainId($state))),

                            Forms\Components\TextInput::make('mobile')
                                ->label('Customer Phone Number')
                                ->maxLength(20)
                                ->reactive()
                                ->required()
                                ->afterStateUpdated(fn($state, callable $set, $get) => $set('complain_id', \App\Filament\Resources\ComplainResource::generateComplainId($get('name'), $state))),

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
                            ->default(fn() => Auth::id())
                            ->disabled(fn() => !auth()->user()->isAdmin() && !auth()->user()->isStoreManager()),

                        Forms\Components\MultiSelect::make('assigned_engineers')
                            ->label('Assigned Engineers')
                            ->dehydrated(true)
                            ->options(fn() => User::role('Engineer')->pluck('name', 'id')->toArray())
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

    // Table to display all complain data with readable names
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('complain_id')
            ->columns([
                Tables\Columns\TextColumn::make('complain_id')->label('Complain ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Customer Name')->sortable(),
                Tables\Columns\TextColumn::make('mobile')->label('Phone')->sortable(),
                Tables\Columns\TextColumn::make('customer_email')->label('Email')->sortable(),
                Tables\Columns\TextColumn::make('address')->label('Address')->sortable(),

                Tables\Columns\TextColumn::make('leadSource.lead_name')->label('Lead Source')->sortable(),

                Tables\Columns\TextColumn::make('device')
                    ->label('Device')
                    ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_type')
                    ->label('Service Type')
                    ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('first_action_code')->label('First Action Code')->sortable(),
                Tables\Columns\TextColumn::make('rsd_time')->label('RSD Time')->sortable(),
                Tables\Columns\TextColumn::make('cancel_reason')->label('Cancel Reason')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->sortable(),

                Tables\Columns\TextColumn::make('estimate_repair_amount')->label('Estimate Repair Amount')->money('usd')->sortable(),
                Tables\Columns\TextColumn::make('estimate_new_amount')->label('Estimate New Amount')->money('usd')->sortable(),

                Tables\Columns\TextColumn::make('assigner.name')->label('Assigned By')->sortable(),
                
                Tables\Columns\TextColumn::make('assigned_engineers')
                    ->label('Assigned Engineers')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return User::whereIn('id', $state)
                                ->pluck('name') // get names
                                ->implode(', '); // convert to comma-separated string
                        }
                        return $state;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => Auth::user()?->isAdmin()), // Only visible to admin
            ])
            ->bulkActions([]);
    }
}
