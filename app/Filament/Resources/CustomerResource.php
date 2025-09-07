<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Suppliers & Customers';

    // Optional: sort order inside group
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Details')->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('phone')->maxLength(20),
                ])->columns(3),
                Forms\Components\Section::make('Billing Address')->schema([
                    Textarea::make('billing_address')->rows(2),
                    TextInput::make('billing_city')->maxLength(100),
                    TextInput::make('billing_state')->maxLength(100),
                    TextInput::make('billing_postal_code')->maxLength(20),
                ])->columns(4),

                Forms\Components\Section::make('Shipping Address')->schema([
                    Textarea::make('shipping_address')->rows(2),
                    TextInput::make('shipping_city')->maxLength(100),
                    TextInput::make('shipping_state')->maxLength(100),
                    TextInput::make('shipping_postal_code')->maxLength(20),
                ])->columns(4),



                Forms\Components\Section::make('GST & PAN Details')->schema([
                    TextInput::make('gstin')->maxLength(15)->unique(ignoreRecord: true),
                    TextInput::make('pan')->maxLength(10)->unique(ignoreRecord: true),

                    TextInput::make('place_of_supply')->maxLength(3)->label('Place of Supply (State Code)'),
                ])->columns(3),

                Forms\Components\Section::make('Contact Person')->schema([
                    TextInput::make('contact_person_name')->maxLength(255),
                    TextInput::make('contact_person_phone')->maxLength(20),
                    TextInput::make('contact_person_email')->email()->maxLength(255),
                ])->columns(3),

                Forms\Components\Section::make('Bank Details')->schema([
                    TextInput::make('bank_account_name')->maxLength(255),
                    TextInput::make('bank_account_number')->maxLength(50),
                    TextInput::make('bank_ifsc')->maxLength(20),
                    TextInput::make('bank_name')->maxLength(255),
                ])->columns(4),


                Forms\Components\Section::make('Limits & Type Details')->schema([
                    Select::make('business_type')
                        ->options([
                            'individual' => 'Individual',
                            'proprietorship' => 'Proprietorship',
                            'partnership' => 'Partnership',
                            'private_limited' => 'Private Limited',
                            'public_limited' => 'Public Limited',
                            'other' => 'Other',
                        ])
                        ->required(),

                    TextInput::make('credit_limit')
                        ->numeric()
                        ->default(0)
                        ->label('Credit Limit'),

                    Textarea::make('notes')->rows(3),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('phone')->label('Phone')->sortable(),
                TextColumn::make('gstin')->label('GSTIN')->sortable(),
                TextColumn::make('business_type')->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])->defaultSort('name')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
