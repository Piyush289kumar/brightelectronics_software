<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplainResource\Pages;
use App\Filament\Resources\ComplainResource\RelationManagers;
use App\Models\Complain;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ComplainResource extends Resource
{
    protected static ?string $model = Complain::class;

    // protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationGroup = 'Complains & Jobs';
    protected static ?string $pluralLabel = 'Complains';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->afterStateUpdated(fn($state, callable $set, $get) => $set('complain_id', self::generateComplainId($get('name'), $state))),

                Forms\Components\TextInput::make('customer_email')
                    ->label('Customer Email')
                    ->email()
                    ->maxLength(255),

                Forms\Components\Textarea::make('address')->label('Customer Address'),

                Forms\Components\TextInput::make('google_map_location')
                    ->label('Customer Google Map Location')
                    ->maxLength(255),

                Forms\Components\TextInput::make('complain_id')
                    ->label('Complain ID')
                    ->disabled()
                    ->required(),

                Forms\Components\MultiSelect::make('device')
                    ->label('Devices')
                    ->options([
                        'TV' => 'TV',
                        'AC' => 'AC',
                        'Fridge' => 'Fridge',
                        'Washing Machine' => 'Washing Machine',
                    ])
                    ->visible(fn($get) => in_array('TV', $get('device') ?? []))
                    ->hint('Only show when device TV is picked'),

                Forms\Components\MultiSelect::make('size')
                    ->label('Sizes')
                    ->options([
                        'Small' => 'Small',
                        'Medium' => 'Medium',
                        'Large' => 'Large',
                    ])
                    ->visible(fn($get) => in_array('TV', $get('device') ?? [])),

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


                Forms\Components\Select::make('first_action_code')
                    ->label('1st Action Code')
                    ->options([
                        'NEW' => 'NEW',
                        'PKD' => 'Picked (PKD)',
                        'Visit' => 'Visit',
                        'RSD' => 'Reschedule Visit (RSD)',
                        'CNC' => 'Call Not Connected (CNC)',
                        'Job Cancel' => 'Job Cancel',
                    ])
                    ->default('NEW')
                    ->required(),

                Forms\Components\DateTimePicker::make('rsd_time')
                    ->label('Reschedule Date & Time')
                    ->visible(fn($get) => $get('first_action_code') === 'RSD'),

                Forms\Components\Textarea::make('cancel_reason')
                    ->label('Cancel Reason')
                    ->visible(fn($get) => $get('first_action_code') === 'Job Cancel'),

                Forms\Components\TextInput::make('pon')
                    ->label('PON')
                    ->maxLength(255),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->default('Pending'),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplains::route('/'),
            // 'create' => Pages\CreateComplain::route('/create'),
            // 'edit' => Pages\EditComplain::route('/{record}/edit'),
        ];
    }


    public static function generateComplainId($name, $mobile = null): string
    {
        $datePart = now()->format('md'); // e.g. 0409
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
