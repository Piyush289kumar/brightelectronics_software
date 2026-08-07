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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\Summarizers\Sum;

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
                    ->label('Branch')
                    ->relationship(
                        name: 'store',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {

                            $user = Auth::user();

                            if (
                                !$user->hasAnyRole([
                                    'Administrator',
                                    'Developer',
                                    'admin',
                                ])
                            ) {
                                $query->where('id', $user->store_id);
                            }

                            return $query;
                        }
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn() => Auth::user()?->store_id)
                    ->disabled(fn() => !Auth::user()?->hasAnyRole([
                        'Administrator',
                        'Developer',
                        'admin',
                    ]))
                    ->dehydrated(true),

                Forms\Components\Select::make('account_id')
                    ->relationship('account', 'account_name') // ✅ Fix here
                    // ->searchable()
                    ->preload()
                    ->required()
                    ->label('Account'),
            ]),


            Grid::make(4)->schema([

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

                Forms\Components\Toggle::make('is_reconciled')
                    ->label('Payment Received')
                    ->helperText('Turn ON after payment has been verified.')
                    ->inline(false)
                    ->default(false)
                    ->onColor('success')
                    ->offColor('danger')
                    ->onIcon('heroicon-o-check-circle')
                    ->offIcon('heroicon-o-x-circle')
                    ->visible(fn() => auth()->user()->hasAnyRole([
                        'Administrator',
                        'Developer',
                        'admin',
                        'Manager',
                        'Store Manager',
                        'Team Leader',
                        'Team Lead',
                    ]))
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
                ->maxSize(10240) // 10 MB
                ->required(fn(Forms\Get $get) => filled($get('payment_reference_number')))
                ->validationMessages([
                    'max' => 'Image size must not exceed 10 MB.',
                    'required' => 'Payment reference image is required when a reference number is entered.',
                ])
                ->columnSpanFull(),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\IconColumn::make('is_reconciled')
                    ->label('Reconciled')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\ImageColumn::make('payment_reference_image_path')
                    ->label('Receipt')
                    ->disk('public')
                    ->square()
                    ->size(50)
                    ->action(
                        Tables\Actions\Action::make('viewImage')
                            ->modalHeading('Receipt')
                            ->modalContent(fn($record) => view(
                                'filament.components.modals.product-image',
                                ['image' => $record->payment_reference_image_path]
                            ))
                            ->modalWidth('xl')
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.account_name')
                    ->label('Account')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('narration')
                    ->limit(40)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),

                Tables\Columns\TextColumn::make('payment_mode')
                    ->label('Mode')
                    ->badge()
                    ->colors([
                        'success' => 'Cash',
                        'info' => 'UPI',
                        'warning' => 'Cheque',
                        'primary' => 'Card',
                        'gray' => 'NEFT',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('INR')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('INR')
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('job_or_complain')
                    ->label('Complaint / Job Card')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Complaint ID or Job Card ID')
                            ->placeholder('Enter Complaint ID or Job Card ID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {

                        if (blank($data['search'])) {
                            return $query;
                        }

                        $search = trim($data['search']);

                        return $query->where(function (Builder $query) use ($search) {

                            // Search by Job Card ID
                            $query->whereHas('jobCard', function (Builder $q) use ($search) {
                                $q->where('job_id', 'like', "%{$search}%");
                            });

                            // OR Search by Complaint ID
                            $query->orWhere(function (Builder $q) use ($search) {

                                // Complaint may not have Job Card yet (Visit Charge)
                                $q->where('narration', 'like', "%{$search}%");

                                // Complaint already has Job Card
                                $q->orWhereHas('jobCard.complain', function (Builder $cq) use ($search) {
                                    $cq->where('complain_id', 'like', "%{$search}%");
                                });
                            });

                        });
                    }),

                Tables\Filters\TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->boolean()
                    ->default(true),

                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'account_name')->label('Account')
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->disabled(function ($record) {

                        $user = auth()->user();

                        // Admins can always edit
                        if (
                            $user->hasAnyRole([
                                'Administrator',
                                'Developer',
                                'admin',
                            ])
                        ) {
                            return false;
                        }
                        // Others cannot edit after reconciliation
                        return $record->is_reconciled;
                    }),
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

    /**
     * Restrict floors listing to manager's store.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if ($user && $user->isStoreManager()) {
            $query->where('store_id', $user->store_id);
        }

        if (
            $user &&
            !$user->hasAnyRole([
                'Administrator',
                'Developer',
                'admin',
                'Team Leader',
                'Team Lead',
            ])
        ) {
            $query->where(function ($q) use ($user) {

                // Ledger linked directly to Complaint (Visit Charge)
                $q->whereHas('complain', function ($complain) use ($user) {
                    $complain->whereJsonContains('assigned_engineers', $user->id);
                });

                // Ledger linked to Job Card
                $q->orWhereHas('jobCard.complain', function ($complain) use ($user) {
                    $complain->whereJsonContains('assigned_engineers', $user->id);
                });
            });
        }

        return $query;
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
