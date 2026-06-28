<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerResource\Pages;
use App\Models\Ledger;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerResource extends Resource
{
    protected static ?string $model = Ledger::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Ledger Entries';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Grid::make(4)->schema([
                ToggleButtons::make('transaction_type')
                    ->label('Type')
                    ->options([
                        'debit' => 'Debit',
                        'credit' => 'Credit',
                    ])
                    ->required()
                    ->grouped()   // Groups buttons together like a toggle
                    ->inline()    // Shows them side-by-side
                    ->default('debit'),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->default(now())
                    ->label('Transaction Date'),


                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name') // ✅ Fix here
                    // ->searchable()
                    ->preload()
                    ->required()
                    ->label('Branch'),

                Forms\Components\Select::make('account_id')
                    ->relationship('account', 'account_name') // ✅ Fix here
                    // ->searchable()
                    ->preload()
                    ->required()
                    ->label('Account'),
            ]),


            Grid::make(3)->schema([

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->prefix('₹')
                    ->label('Amount'),


                Forms\Components\Select::make('payment_mode')
                    ->label('Payment Mode')
                    ->options([
                        'Cash' => 'Cash',
                        'UPI' => 'UPI',
                        'Cheque' => 'Cheque',
                        'Card' => 'Card',
                        'NEFT' => 'NEFT',
                        'RTGS' => 'RTGS',
                        'IMPS' => 'IMPS',
                        'Bank Transfer' => 'Bank Transfer',
                        'Wallet' => 'Wallet',
                    ])
                    ->searchable(),

                Forms\Components\TextInput::make('reference')
                    ->label('Reference Number')
                    ->placeholder('UTR / UPI / Cheque No.')
                    ->visible(fn(Forms\Get $get) => $get('payment_mode') !== 'Cash'),
            ]),

            Forms\Components\TextInput::make('narration')
                ->label('Narration')
                ->columnSpanFull(),

            Forms\Components\FileUpload::make('payment_reference_image_path')
                ->label('Payment Reference Image')
                ->image()
                ->previewable(true)
                ->nullable()
                ->directory('payment-references')
                ->disk('public')
                ->visibility('public')
                ->imagePreviewHeight('150')
                ->downloadable()
                ->openable()
                ->acceptedFileTypes([
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ])
                ->maxSize(2048)
                ->required(fn(Forms\Get $get) => filled($get('payment_reference_number')))
                ->validationMessages([
                    'required' => 'Payment reference image is required when a reference number is entered.',
                ])
                ->columnSpanFull(),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('narration')->limit(50)->toggleable(),
                Tables\Columns\TextColumn::make('store.name')->label('Branch')->searchable()->sortable()->toggleable(),
                // Tables\Columns\TextColumn::make('account.account_name')->label('Account')->searchable(),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ])
                    ->label('Type'),

                Tables\Columns\TextColumn::make('amount')->money('inr')->sortable()->toggleable(),

                // Tables\Columns\TextColumn::make('balance')->money('inr')->sortable(),

                // Tables\Columns\TextColumn::make('journalEntry.reference')
                //     ->label('Reference')
                //     ->toggleable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')->label('Branch'),
                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'account_name')->label('Account')
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgers::route('/'),
            // 'create' => Pages\CreateLedger::route('/create'),
            // 'edit' => Pages\EditLedger::route('/{record}/edit'),
        ];
    }
}
