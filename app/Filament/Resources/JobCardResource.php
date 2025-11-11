<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobCardResource\Pages;
use App\Models\JobCard;
use App\Models\Product;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class JobCardResource extends Resource
{
    protected static ?string $model = JobCard::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Complains & Jobs';
    protected static ?string $pluralLabel = 'Job Cards';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Job Details')
                ->description('Core information for the job card and complain mapping.')
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\Select::make('complain_id')
                            ->label('Complain')
                            ->relationship('complain', 'complain_id')
                            ->required()
                            ->disabled(fn($record) => filled($record))
                            ->dehydrated(true)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('job_id')
                            ->label('Job ID')
                            ->disabled()
                            ->required()
                            ->dehydrated(true)
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Open' => 'Open',
                                'In Progress' => 'In Progress',
                                'Completed' => 'Completed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Open')
                            ->columnSpan(1),
                    ]),
                ])
                ->columns(3)
                ->collapsible(),

            Forms\Components\Section::make('Financials & GST')
                ->description('Manage pricing, product expenses, and tax calculations.')
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Job Amount (₹)')
                            ->numeric()
                            ->lazy()
                            ->reactive()
                            ->afterStateUpdated(fn($state, $set, $get) => self::recalculateAll($set, $get))
                            ->columnSpan(1),

                        Forms\Components\Select::make('product_id')
                            ->label('Select Products (Expenses)')
                            ->options(Product::pluck('name', 'id'))
                            ->multiple()
                            ->reactive()
                            ->dehydrated()
                            ->afterStateUpdated(fn($state, $set, $get) => self::recalculateAll($set, $get))
                            ->columnSpan(2),
                    ]),

                    Grid::make(5)->schema([
                        Forms\Components\TextInput::make('expense')
                            ->label('Product Expense (₹)')
                            ->disabled()
                            ->dehydrated()
                            ->reactive(),

                        Forms\Components\TextInput::make('gst_amount')
                            ->label('GST Amount (18%)')
                            ->disabled()
                            ->dehydrated()
                            ->reactive(),

                        Forms\Components\TextInput::make('gross_amount')
                            ->label('Gross After Expense (₹)')
                            ->disabled()
                            ->dehydrated()
                            ->reactive(),

                        Forms\Components\TextInput::make('lead_incentive_percent')
                            ->label('Lead Source Incentive %')
                            ->dehydrated()
                            ->disabled()
                            ->afterStateHydrated(function ($state, $set, $get, $record) {
                                if ($record && $record->complain && $record->complain->leadSource) {
                                    $set('lead_incentive_percent', round($record->complain->leadSource->lead_incentive, 2));
                                } else {
                                    $set('lead_incentive_percent', 0);
                                }
                            }),

                        Forms\Components\TextInput::make('lead_incentive_amount')
                            ->label('Lead Incentive Amt. (₹)')
                            ->disabled()
                            ->dehydrated()
                            ->reactive(),

                    ]),

                ])
                ->columns(3)
                ->collapsible(),

            Forms\Components\Section::make('Engineer Incentives')
                ->description('Assign engineers, set incentive percentages, and review summary details.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            // 🧑‍🔧 Left Column — Engineer Incentive Breakdown
                            Forms\Components\Repeater::make('incentive_percentages')
                                ->label('Engineer Incentive Breakdown')
                                ->dehydrated()
                                ->columnSpan(1)
                                ->schema([
                                    Grid::make(3)->schema([
                                        Forms\Components\Select::make('engineer_id')
                                            ->label('Engineer')
                                            ->options(User::role('Engineer')->pluck('name', 'id')->toArray())
                                            ->required()
                                            ->searchable(),

                                        Forms\Components\TextInput::make('percent')
                                            ->label('Incentive %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->reactive(),

                                        Forms\Components\TextInput::make('amount')
                                            ->label('Incentive Amount (₹)')
                                            ->readonly()
                                            ->reactive(),
                                    ]),
                                ])
                                ->afterStateHydrated(function ($state, $set, $get, $record) {
                                    if (empty($state)) {
                                        $complain = $record?->complain;
                                        if ($complain && is_array($complain->assigned_engineers)) {
                                            $newEngineers = [];
                                            foreach ($complain->assigned_engineers as $engineerId) {
                                                $newEngineers[] = [
                                                    'engineer_id' => $engineerId,
                                                    'percent' => null,
                                                    'amount' => 0.00,
                                                ];
                                            }
                                            $set('incentive_percentages', $newEngineers);
                                        }
                                    }
                                })
                                ->afterStateUpdated(fn($state, $set, $get) => self::recalculateAll($set, $get)),

                            // 💰 Right Column — Summary & Verification
                            Forms\Components\Card::make()
                                ->schema([
                                    Grid::make(1)->schema([
                                        Forms\Components\TextInput::make('incentive_amount')
                                            ->label('Total Engineer Incentive (₹)')
                                            ->disabled()
                                            ->dehydrated()
                                            ->reactive()
                                            ->suffixIcon('heroicon-o-currency-rupee')
                                            ->extraAttributes(['class' => 'text-green-600 font-semibold']),

                                        Forms\Components\TextInput::make('bright_electronics_profit')
                                            ->label('Bright Electronics Profit (₹)')
                                            ->disabled()
                                            ->dehydrated()
                                            ->reactive()
                                            ->suffixIcon('heroicon-o-currency-rupee')
                                            ->extraAttributes(['class' => 'text-blue-600 font-semibold']),

                                        Forms\Components\Toggle::make('job_verified_by_admin')
                                            ->label('Verified by Admin')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->inline(false)
                                            ->helperText('Enable this switch when the job card is verified by admin.')
                                            ->dehydrated(),
                                    ]),
                                ])
                                ->columnSpan(1),
                        ]),
                ])
                ->columns(2)
                ->collapsible()
                ->compact(),

            Forms\Components\Section::make('Additional Notes')
                ->description('Add any comments or admin remarks related to this job card.')
                ->schema([
                    Forms\Components\Textarea::make('note')
                        ->label('Notes / Remarks')
                        ->rows(3),
                ]),
        ]);
    }

    /**
     * --- Centralized calculation logic
     */
    protected static function recalculateAll($set, $get)
    {
        $amount = round((float) ($get('amount') ?? 0), 2);
        $productIds = $get('product_id') ?? [];

        // Expense = sum of selected product selling prices
        $expense = Product::whereIn('id', $productIds)->sum('selling_price');
        $expense = round($expense, 2);

        // GST fixed 18% of amount
        $gstAmount = round(($amount * 18) / 100, 2);

        // Gross = amount - expense (not including GST)
        $gross = round($amount - $expense, 2);

        $set('gst_amount', $gstAmount);
        $set('expense', $expense);
        $set('gross_amount', $gross);

        // Lead incentive
        $leadPercent = round((float) ($get('lead_incentive_percent') ?? 0), 2);
        $leadIncentiveAmount = round(($gross * $leadPercent) / 100, 2);
        $set('lead_incentive_amount', $leadIncentiveAmount);

        // Engineer Incentives
        $engineers = $get('incentive_percentages') ?? [];
        $totalStaffIncentive = 0;
        foreach ($engineers as $i => $row) {
            $percent = isset($row['percent']) ? (float) $row['percent'] : 0;
            $incentiveAmt = round(($percent / 100) * $gross, 2);
            $engineers[$i]['amount'] = $incentiveAmt;
            $totalStaffIncentive += $incentiveAmt;
        }
        $set('incentive_percentages', $engineers);
        $set('incentive_amount', round($totalStaffIncentive, 2));

        // Final Bright Electronics Profit
        $brightProfit = round($gross - $leadIncentiveAmount - $totalStaffIncentive, 2);
        $set('bright_electronics_profit', $brightProfit);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 🧾 Identification
                Tables\Columns\TextColumn::make('job_id')
                    ->label('Job ID')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('complain.complain_id')
                    ->label('Complain ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Open' => 'warning',
                        'In Progress' => 'info',
                        'Completed' => 'success',
                        'Cancelled' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable()
                    ->toggleable(),

                // 💰 Financial Fields
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (₹)')
                    ->money('inr')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('gst_amount')
                    ->label('GST (18%)')
                    ->money('inr')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expense')
                    ->label('Expense (₹)')
                    ->money('inr')
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross (₹)')
                    ->money('inr')
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lead_incentive_percent')
                    ->label('Lead Incentive (%)')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lead_incentive_amount')
                    ->label('Lead Incentive Amount (₹)')
                    ->money('inr')
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('incentive_amount')
                    ->label('Engineer Incentive Total (₹)')
                    ->money('inr')
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('bright_electronics_profit')
                    ->label('Bright Electronics Profit (₹)')
                    ->money('inr')
                    ->color('success')
                    ->weight('bold')
                    ->alignRight()
                    ->toggleable(),

                // ✅ Verification
                Tables\Columns\IconColumn::make('job_verified_by_admin')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->toggleable(),

                // 📅 Timestamps
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('d M Y, h:i A')
                    ->toggleable(),
            ])

            // 🔍 Filters
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Open' => 'Open',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->searchable(),

                Tables\Filters\Filter::make('verified')
                    ->label('Verified by Admin')
                    ->toggle()
                    ->query(fn($query) => $query->where('job_verified_by_admin', true)),

                Tables\Filters\Filter::make('created_date')
                    ->label('Created Date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(
                        fn($query, array $data) =>
                        $query
                            ->when($data['from'], fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['to'], fn($q) => $q->whereDate('created_at', '<=', $data['to']))
                    ),
            ])

            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->toggleColumnsTriggerAction(fn($action) => $action->label('Toggle Columns'))
            ->defaultSort('job_id', 'desc')
            ->striped()
            // ⚙️ Actions
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->dropdown()->tooltip('Actions')
            ])

            // 📦 Bulk Actions
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()->label('Export'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobCards::route('/'),
            'create' => Pages\CreateJobCard::route('/create'),
            'edit' => Pages\EditJobCard::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['complain.leadSource']);
    }
}
