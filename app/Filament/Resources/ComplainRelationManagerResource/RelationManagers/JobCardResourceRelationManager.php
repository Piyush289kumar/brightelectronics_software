<?php

namespace App\Filament\Resources\ComplainRelationManagerResource\RelationManagers;

use Filament\Forms;
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
                Forms\Components\TextInput::make('name')
                    ->label('Customer Name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\TextInput::make('mobile')
                    ->label('Phone Number')
                    ->required()
                    ->maxLength(20)
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\TextInput::make('customer_email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\Textarea::make('address')
                    ->label('Address')
                    ->rows(3)
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\Select::make('lead_source_id')
                    ->label('Lead Source')
                    ->relationship('leadSource', 'lead_name')
                    ->required()
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\MultiSelect::make('device')
                    ->label('Device')
                    ->options([
                        'TV' => 'TV',
                        'AC' => 'AC',
                        'Fridge' => 'Fridge',
                        // Add more device options as needed
                    ])
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\MultiSelect::make('service_type')
                    ->label('Service Type')
                    ->options([
                        'Repair' => 'Repair',
                        'Installation' => 'Installation',
                        'Maintenance' => 'Maintenance',
                        // Add more service types as needed
                    ])
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\Select::make('assigned_by')
                    ->label('Assigned By')
                    ->relationship('assigner', 'name')
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\MultiSelect::make('assigned_engineers')
                    ->label('Assigned Engineers')
                    ->options(function () {
                        return \App\Models\User::role('Engineer')->pluck('name', 'id')->toArray();
                    })
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\TextInput::make('estimate_repair_amount')
                    ->label('Estimate Repair Amount')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->disabled(fn() => !Auth::user()?->isAdmin()),

                Forms\Components\TextInput::make('estimate_new_amount')
                    ->label('Estimate New Amount')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->disabled(fn() => !Auth::user()?->isAdmin()),
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
                    ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state)
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
