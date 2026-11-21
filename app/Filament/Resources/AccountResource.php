<?php

namespace App\Filament\Resources;


use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends BaseResource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Accounts';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('General Information')
                ->description('Basic details about this account.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('account_name')
                            ->label('Account Name')
                            ->placeholder('e.g. Primary Bank, Cash, UPI Wallet')
                            ->required()
                            ->maxLength(191),

                        Forms\Components\Select::make('account_type')
                            ->label('Account Type')
                            ->options([
                                'cash' => 'Cash',
                                'bank' => 'Bank',
                                'upi' => 'UPI',
                                'credit_card' => 'Credit Card',
                                'other' => 'Other',
                            ])
                            ->default('bank')
                            ->required()
                            ->live(),
                    ]),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Bank Details')
                ->description('Fill these details for bank accounts.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->placeholder('e.g. HDFC, SBI, ICICI')
                            ->maxLength(191),

                        Forms\Components\TextInput::make('branch_name')
                            ->label('Branch Name')
                            ->maxLength(191),

                        Forms\Components\TextInput::make('branch_code')
                            ->label('Branch Code')
                            ->maxLength(191),

                        Forms\Components\TextInput::make('ifsc_code')
                            ->label('IFSC Code')
                            ->maxLength(191),

                        Forms\Components\TextInput::make('swift_code')
                            ->label('SWIFT Code')
                            ->maxLength(191),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Account Number')
                            ->maxLength(191),
                    ]),

                    Forms\Components\Textarea::make('branch_address')
                        ->label('Branch Address')
                        ->rows(2)
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Forms\Components\Section::make('UPI / Digital Payment')
                ->description('Optional for UPI accounts.')
                ->schema([
                    Forms\Components\TextInput::make('upi_id')
                        ->label('UPI ID')
                        ->placeholder('example@upi')
                        ->maxLength(191),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Balance & Status')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('opening_balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('₹')
                            ->label('Opening Balance')
                            ->required(),

                        Forms\Components\Select::make('balance_type')
                            ->label('Opening Balance Type')
                            ->options([
                                'debit' => 'Debit',
                                'credit' => 'Credit',
                            ])
                            ->default('debit')
                            ->required(),

                        Forms\Components\TextInput::make('current_balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('₹')
                            ->label('Current Balance')
                            ->required(),
                    ]),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Active Account?'),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('account_type')
                    ->label('Account Type')
                    ->colors([
                        'success' => 'bank',
                        'warning' => 'cash',
                        'info' => 'upi',
                        'danger' => 'credit_card',
                        'gray' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch_name')->label('Branch Name'),
                Tables\Columns\TextColumn::make('branch_code')->label('Branch Code'),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account Number'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->money('INR')
                    ->sortable()
                    ->label('Current Balance'),

                Tables\Columns\TextColumn::make('opening_balance')
                    ->money('INR')
                    ->sortable()
                    ->label('Opening Balance'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
