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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use TomatoPHP\FilamentDocs\Models\Document;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;
use TomatoPHP\FilamentDocs\Filament\Resources\DocumentResource\Pages\PrintDocument;

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
                ->schema([
                    Grid::make(3)->schema([

                        Forms\Components\Select::make('complain_id')
                            ->label('Complain')
                            ->relationship('complain', 'complain_id')
                            ->required()
                            ->disabled(fn($record) => filled($record))
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('job_id')
                            ->label('Job ID')
                            ->disabled()
                            ->required()
                            ->dehydrated(true),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(function () {
                                $user = auth()->user();
                                $options = [
                                    'Pending' => 'Pending',
                                    'Complete' => 'Complete',
                                    'Return' => 'Return',
                                    'Out_for_delivery' => 'Out for Delivery',
                                    'Cancelled' => 'Cancelled',
                                ];
                                if ($user && $user->hasAnyRole(['Administrator', 'admin', 'Manager', 'Team Lead'])) {
                                    $options['Delivered'] = 'Delivered';
                                }
                                return $options;
                            })
                            ->default('Pending')
                            ->required(),

                        Forms\Components\CheckboxList::make('check_list')
                            ->label('Accessories Checklist')
                            ->options([
                                'Remote' => 'Remote',
                                'Remote Battery' => 'Remote Battery',
                                'Adapter' => 'Adapter',
                                'Power Cable' => 'Power Cable',
                                'Wall Stand' => 'Wall Stand',
                                'Table Stand' => 'Table Stand',
                                'Box' => 'Box',
                            ])
                            ->columns(7) // ✅ 1 row (7 items = 1 line)
                            ->columnSpanFull()
                            ->dehydrated(true)
                    ]),
                ])
                ->columns(3)
                ->collapsible(),

            Forms\Components\Section::make('Financials & GST')
                ->schema([

                    Grid::make(3)->schema([

                        Forms\Components\TextInput::make('visit_charge_amount')
                            ->numeric()
                            ->dehydrated(true)
                            ->label('Visit Charge Amount (₹)')
                            ->reactive()
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set, callable $get) =>
                                \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                            ),

                        Forms\Components\TextInput::make('on_delivery_amount')
                            ->numeric()
                            ->default(0)
                            ->dehydrated(true)
                            ->label('On Delivery Amount (₹)')
                            ->reactive()
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set, callable $get) =>
                                \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                            ),

                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->dehydrated(true)
                            ->label('Job Amount (₹)')
                            ->reactive()
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set, callable $get) =>
                                \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                            ),
                    ]),

                    Forms\Components\Section::make('Lead Information')
                        ->icon('heroicon-o-user-group')
                        ->schema([

                            Grid::make(3)->schema([

                                Forms\Components\Placeholder::make('lead_source_name')
                                    ->label('Lead Source')
                                    ->content(
                                        fn($record) =>
                                        $record?->complain?->leadSource?->lead_name ?? 'N/A'
                                    )
                                    ->extraAttributes([
                                        'class' => 'text-primary-600 font-semibold text-sm'
                                    ]),

                                Forms\Components\TextInput::make('lead_incentive_percent')
                                    ->label('Commission')
                                    ->suffix('%')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefixIcon('heroicon-o-percent-badge')
                                    ->extraAttributes([
                                        'class' => 'font-semibold text-warning-600'
                                    ]),

                                Forms\Components\TextInput::make('lead_incentive_amount')
                                    ->label('Lead Earnings')
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefixIcon('heroicon-o-currency-rupee')
                                    ->extraAttributes([
                                        'class' => 'font-bold text-danger-600'
                                    ]),
                            ]),

                            Forms\Components\Section::make('Engineer Incentives')
                                ->icon('heroicon-o-users')
                                ->schema([

                                    Forms\Components\Repeater::make('incentive_percentages')
                                        ->dehydrated(true)
                                        ->label('Engineers')
                                        ->schema([

                                            Forms\Components\Select::make('user_id')
                                                ->label('Engineer')
                                                ->options(function ($get) {

                                                    $complainId = $get('../../complain_id');

                                                    $assigned = [];

                                                    if ($complainId) {
                                                        $complain = \App\Models\Complain::find($complainId);
                                                        $assigned = $complain?->assigned_engineers ?? [];
                                                    }

                                                    return \App\Models\User::role(['Engineer', 'Machine Men'])
                                                        ->get()
                                                        ->sortByDesc(fn($u) => in_array($u->id, $assigned)) // assigned first
                                                        ->mapWithKeys(fn($u) => [
                                                            $u->id => in_array($u->id, $assigned)
                                                                ? "⭐ {$u->name}"
                                                                : $u->name
                                                        ])
                                                        ->toArray();
                                                })
                                                ->searchable()
                                                ->required()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(), // 🔥 prevent duplicate

                                            // Forms\Components\TextInput::make('percent')
                                            //     ->label('Incentive %')
                                            //     ->numeric()
                                            //     ->suffix('%')
                                            //     ->required()
                                            //     ->reactive()
                                            //     ->afterStateUpdated(
                                            //         fn($set, $get) =>
                                            //         \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                                            //     ),

                                            Forms\Components\TextInput::make('percent')
                                                ->label('Incentive %')
                                                ->dehydrated(true),

                                            // ✅ SAVE होने वाला field
                                            Forms\Components\Hidden::make('amount')
                                                ->dehydrated(true),

                                            // ✅ UI display
                                            Forms\Components\Placeholder::make('amount_display')
                                                ->label('Amount')
                                                ->content(fn($get) => "₹ " . ($get('amount') ?? 0)),

                                        ])
                                        ->columns(3)
                                        ->addActionLabel('Add Engineer')
                                        ->reorderable(false)
                                        ->reactive()
                                        ->afterStateUpdated(
                                            fn($set, $get) =>
                                            \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                                        ),

                                ])
                                ->columnSpanFull(),

                        ])
                        ->collapsible()
                        ->collapsed(false)
                        ->columnSpanFull(),

                    // ✅ KEY FIX: Repeater uses ->relationship-style static options
                    // NO getSearchResultsUsing inside live() repeater — that causes closure serialization
                    Forms\Components\Repeater::make('spare_parts')
                        ->label('Spare Parts')
                        ->addable(false)
                        ->dehydrated(true)
                        ->afterStateHydrated(function ($state, $set, $get) {

                            if (request()->has('components')) {
                                return;
                            }

                            \App\Filament\Resources\JobCardResource::recalculateAll(
                                $set,
                                $get
                            );

                        })
                        ->addActionLabel('Add Spare Part')
                        ->schema([

                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(
                                    Product::with('category.parent')
                                        ->get()
                                        ->mapWithKeys(fn($product) => [
                                            $product->id => static::getProductLabel($product->id)
                                        ])
                                        ->toArray()
                                )
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(
                                    fn($state, $set, $get) =>
                                    \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                                ),

                            Forms\Components\TextInput::make('unit_rate')
                                ->label('Unit Rate')
                                ->prefix('₹')
                                ->disabled()
                                ->dehydrated(false)
                                ->reactive()
                                ->afterStateHydrated(function ($set, $get) {

                                    $productId = $get('product_id');

                                    if (!$productId) {
                                        $set('unit_rate', 0);
                                        return;
                                    }

                                    $product = Product::find($productId);

                                    $set('unit_rate', $product?->selling_price ?? 0);
                                }),

                            Forms\Components\TextInput::make('qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->reactive()
                                ->afterStateUpdated(fn($set, $get) => \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)),


                            Forms\Components\TextInput::make('part_total')
                                ->label('Total')
                                ->disabled()
                                ->dehydrated(true)
                                ->reactive()
                                ->afterStateHydrated(function ($set, $get) {
                                    $qty = (int) ($get('qty') ?? 0);
                                    $product = Product::find($get('product_id'));
                                    $rate = $product?->selling_price ?? 0;
                                    $set('part_total', '₹' . number_format($qty * $rate, 2));
                                }),

                        ])
                        ->defaultItems(0)
                        ->collapsed()
                        ->columns(4)
                        ->reorderable(false)
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(fn($set, $get) => self::recalculateAll($set, $get)),

                    Grid::make(5)->schema([
                        Forms\Components\TextInput::make('expense')
                            ->label('Product Expense (₹)')
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive(),

                        Forms\Components\TextInput::make('gst_amount')
                            ->label('GST Amount (18%)')
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive(),

                        Forms\Components\TextInput::make('gross_amount')
                            ->label('Gross After Expense (₹)')
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive(),

                        Forms\Components\TextInput::make('incentive_amount')
                            ->label('Engineer Total Cut (₹)')
                            ->disabled()
                            ->dehydrated(true)
                            ->prefix('₹')
                            ->extraAttributes([
                                'class' => 'font-bold text-warning-600'
                            ]),

                        Forms\Components\TextInput::make('bright_electronics_profit')
                            ->label('Profit (₹)')
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive(),

                        Forms\Components\TextInput::make('payment_reference_number')
                            ->label('Payment Reference Number')
                            ->placeholder('Enter UPI / Transaction Ref No.')
                            ->maxLength(255)
                            ->columnSpan(2),

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
                            ->columnSpan(3),

                    ]),
                ])
                ->collapsible(),
        ])->disabled(fn() => !auth()->user()->hasAnyRole(['Administrator', 'Team Lead']));
    }

    protected static function calculateExpense($get)
    {
        $products = collect($get('spare_parts') ?? []);

        if ($products->isEmpty())
            return 0;

        $productIds = $products->pluck('product_id')->filter()->unique()->toArray();

        $productData = Product::whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        return round(
            $products->sum(function ($row) use ($productData) {
                $id = $row['product_id'] ?? null;
                $qty = max(1, (int) ($row['qty'] ?? 1));

                if (!$id || !isset($productData[$id]))
                    return 0;

                return (float) $productData[$id]->selling_price * $qty;
            }),
            2
        );
    }
    protected static function calculateGST($get)
    {
        $amount = (float) ($get('amount') ?? 0);
        return round(($amount * 18) / 100, 2);
    }

    protected static function calculateGross($get)
    {
        $amount = (float) ($get('amount') ?? 0);
        $expense = self::calculateExpense($get);

        return round($amount - $expense, 2);
    }
    /**
     * Centralized recalculation — reads from 'spare_parts', sets all totals
     */

    protected static function recalculateAll($set, $get): void
    {

        $visitCharge = (float) ($get('visit_charge_amount') ?? 0);
        $onDeliveryAmount = (float) ($get('on_delivery_amount') ?? 0);

        // Job Amount = On Delivery Amount - Visit Charge
        $amount = max(0, round($onDeliveryAmount - $visitCharge, 2));

        // Update the amount field automatically
        $set('amount', $amount);

        // $amount = round((float) ($get('amount') ?? 0), 2);

        // =============================
        // ✅ EXPENSE CALCULATION
        // =============================
        // $products = collect($get('spare_parts') ?? []);

        $products = collect($get('spare_parts') ?: []);

        $products = $products->map(function ($row) {
            $id = $row['product_id'] ?? null;

            if (is_array($id)) {
                $id = $id['value'] ?? null;
            }

            return [
                'product_id' => (int) $id,
                'qty' => max(1, (int) ($row['qty'] ?? 1)),
            ];
        })->filter(fn($p) => !empty($p['product_id']));

        $productData = \App\Models\Product::whereIn('id', $products->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $expense = round(
            $products->sum(function ($row) use ($productData) {
                $product = $productData[$row['product_id']] ?? null;
                return $product ? $product->selling_price * $row['qty'] : 0;
            }),
            2
        );

        // =============================
        // ✅ GST (DISPLAY ONLY)
        // =============================
        $gstAmount = round(($amount * 18) / 100, 2);

        // =============================
        // ✅ PROFIT BEFORE LEAD
        // =============================
        $profit = $amount - $expense;

        // =============================
        // ✅ LEAD CALCULATION (ON PROFIT)
        // =============================
        $complainId = $get('complain_id');
        $leadPercent = 0;

        if ($complainId) {
            $complain = \App\Models\Complain::with('leadSource')->find($complainId);
            $leadPercent = (float) ($complain?->leadSource?->lead_incentive ?? 0);
        }

        $leadAmount = round(($profit * $leadPercent) / 100, 2);

        // =============================
        // ✅ AFTER LEAD PROFIT
        // =============================
        $afterLeadProfit = $profit - $leadAmount;

        // =============================
        // ✅ ENGINEER + MACHINE MAN LOGIC
        // =============================
        $engineers = $get('incentive_percentages') ?? [];

        $userIds = collect($engineers)->pluck('user_id')->filter()->toArray();

        $users = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');

        // 🔥 USE SAME ROLE NAME EVERYWHERE
        $ROLE_MACHINE = 'Machine Men';

        // 🔥 Detect Machine Man
        $machineManExists = $users->contains(fn($user) => $user->hasRole($ROLE_MACHINE));

        $totalEngineerAmount = 0;

        foreach ($engineers as $index => $row) {

            $user = $users[$row['user_id']] ?? null;

            if (!$user)
                continue;

            $basePercent = (float) ($user->incentive ?? 0);

            // 🎯 RULE APPLY
            if ($machineManExists) {

                if ($user->hasRole($ROLE_MACHINE)) {
                    // ✅ Machine Man → FULL %
                    $percent = $basePercent;
                } else {
                    // ✅ Engineers → HALF %
                    $percent = $basePercent / 2;
                }

            } else {
                // ✅ Normal case (no machine man)
                $percent = $basePercent;
            }

            $amountCalc = round(($afterLeadProfit * $percent) / 100, 2);

            $engineers[$index]['percent'] = $percent;
            $engineers[$index]['amount'] = $amountCalc;

            $totalEngineerAmount += $amountCalc;
        }

        // =============================
        // ✅ FINAL COMPANY PROFIT
        // =============================
        $companyProfit = $afterLeadProfit - $totalEngineerAmount;

        // =============================
        // ✅ SET VALUES
        // =============================
        $set('incentive_percentages', $engineers);
        $set('incentive_amount', round($totalEngineerAmount, 2));

        $set('lead_incentive_percent', $leadPercent);
        $set('lead_incentive_amount', $leadAmount);

        $set('bright_electronics_profit', round($companyProfit, 2));

        $set('expense', $expense);
        $set('gst_amount', $gstAmount);
        $set('gross_amount', $profit);
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('job_id')
                    ->label('Job ID')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                // new

                Tables\Columns\TextColumn::make('complain.complain_id')
                    ->label('Complain ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('complain.name')
                    ->label('Customer Name')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('complain.mobile')
                    ->label('Customer Phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('complain.leadSource.lead_name')
                    ->label('Lead Source')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assigned_engineers_text')
                    ->label('Assigned Engineers')
                    ->state(fn($record) => static::assignedEngineersText($record->complain?->assigned_engineers ?? []))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('check_list_text')
                    ->label('Checklist')
                    ->state(fn($record) => static::checklistText($record->check_list ?? []))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('spare_parts_text')
                    ->label('Spare Parts')
                    ->state(fn($record) => static::sparePartsText($record->spare_parts ?? []))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('incentive_percentages_text')
                    ->label('Engineer Incentives')
                    ->state(fn($record) => static::incentiveBreakdown($record->incentive_percentages ?? []))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_reference_number')
                    ->label('Payment Ref No.')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('visit_charge_amount')
                    ->label('Visit Charge (₹)')
                    ->money('INR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Job Amount (₹)')
                    ->money('INR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('gst_amount')
                    ->label('GST Amount (₹)')
                    ->money('INR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expense')
                    ->label('Expense (₹)')
                    ->money('INR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross Amount (₹)')
                    ->money('INR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lead_incentive_percent')
                    ->label('Lead %')
                    ->suffix('%')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lead_incentive_amount')
                    ->label('Lead Incentive (₹)')
                    ->money('INR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('incentive_amount')
                    ->label('Engineer Incentive (₹)')
                    ->money('INR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('bright_electronics_profit')
                    ->label('Profit (₹)')
                    ->money('INR')
                    ->toggleable(),

                // new
                // Tables\Columns\TextColumn::make('complain.complain_id')
                //     ->label('Complain ID')
                //     ->sortable()
                //     ->searchable()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('status')
                //     ->label('Status')
                //     ->badge()
                //     ->color(fn($state) => match ($state) {
                //         'Pending' => 'warning',
                //         'Complete' => 'success',
                //         'Return' => 'danger',
                //         'Cancelled' => 'danger',
                //         default => 'secondary',
                //     })
                //     ->sortable()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('amount')
                //     ->label('Amount (₹)')
                //     ->money('inr')
                //     ->sortable()
                //     ->alignRight()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('gst_amount')
                //     ->label('GST (18%)')
                //     ->money('inr')
                //     ->alignRight()
                //     ->sortable()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('expense')
                //     ->label('Expense (₹)')
                //     ->money('inr')
                //     ->alignRight()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('gross_amount')
                //     ->label('Gross (₹)')
                //     ->money('inr')
                //     ->alignRight()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('lead_incentive_percent')
                //     ->label('Lead Incentive (%)')
                //     ->suffix('%')
                //     ->sortable()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('lead_incentive_amount')
                //     ->label('Lead Incentive Amount (₹)')
                //     ->money('inr')
                //     ->alignRight()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('incentive_amount')
                //     ->label('Engineer Incentive Total (₹)')
                //     ->money('inr')
                //     ->alignRight()
                //     ->toggleable(),
                // Tables\Columns\TextColumn::make('bright_electronics_profit')
                //     ->label('Bright Electronics Profit (₹)')
                //     ->money('inr')
                //     ->color('success')
                //     ->weight('bold')
                //     ->alignRight()
                //     ->toggleable(),
                // Tables\Columns\IconColumn::make('job_verified_by_admin')
                //     ->label('Verified')
                //     ->boolean()
                //     ->trueIcon('heroicon-o-check-circle')
                //     ->falseIcon('heroicon-o-x-circle')
                //     ->trueColor('success')
                //     ->falseColor('danger')
                //     ->sortable()
                //     ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(),
                // Tables\Columns\TextColumn::make('updated_at')
                //     ->label('Updated At')
                //     ->dateTime('d M Y, h:i A')
                //     ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'Complete' => 'Complete',
                        'Delivered' => 'Delivered',
                        'Return' => 'Return',
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
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('toggleVerify')
                        ->label(fn($record) => $record->job_verified_by_admin ? 'Unverify' : 'Verify')
                        ->icon(fn($record) => $record->job_verified_by_admin ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->update(['job_verified_by_admin' => !$record->job_verified_by_admin]))
                        ->color(fn($record) => $record->job_verified_by_admin ? 'danger' : 'success')
                        ->visible(fn() => auth()->user()->hasAnyRole(['Administrator', 'Store Manager', 'Team Lead'])),
                    Tables\Actions\Action::make('generateAndViewJobCard')
                        ->label('Preview Job Card')
                        ->color('info')
                        ->icon('heroicon-s-eye')
                        ->modalHeading('Job Card Preview')
                        ->modalButton('Print')
                        ->action(function ($record, $livewire) {
                            $url = PrintDocument::getUrl(['record' => $record->document_id]);
                            $livewire->js(<<<JS
                                const iframe = document.createElement('iframe');
                                iframe.style.cssText = 'position:absolute;width:0;height:0;border:0';
                                iframe.src = "{$url}";
                                document.body.appendChild(iframe);
                                iframe.onload = () => { iframe.contentWindow.focus(); iframe.contentWindow.print(); };
                            JS);
                        })
                        ->modalContent(function ($record) {
                            $template = DocumentTemplate::find(15);
                            $templateBody = (string) ($template->body ?? '');
                            $check = $record->check_list ?? [];
                            $map = [
                                '$DOCUMENT_DATE' => now()->format('d-m-Y'),
                                '$JOB_ID' => $record->job_id,
                                '$COMPLAIN_ID' => $record->complain->complain_id ?? '',
                                '$CUSTOMER_NAME' => $record->complain->name ?? '',
                                '$CUSTOMER_PHONE' => $record->complain->mobile ?? '',
                                '$DEVICE' => $record->complain->device ?? '',
                                '$SERVICES' => implode(', ', $record->complain->service_type ?? []),
                                '$ESTIMATE_REPAIR_AMOUNT' => $record->complain->estimate_repair_amount ?? '',
                                '$ESTIMATE_NEW_AMOUNT' => $record->complain->estimate_new_amount ?? '',
                                '$REMOTE' => in_array('Remote', $check) ? '✔' : '✘',
                                '$REMOTE_BATTERY' => in_array('Remote Battery', $check) ? '✔' : '✘',
                                '$ADAPTER' => in_array('Adapter', $check) ? '✔' : '✘',
                                '$POWERCABLE' => in_array('Power Cable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wall Stand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table Stand', $check) ? '✔' : '✘',
                                '$BOX' => in_array('Box', $check) ? '✔' : '✘',
                            ];
                            uksort($map, fn($a, $b) => strlen($b) - strlen($a));
                            $body = $templateBody;
                            foreach ($map as $k => $v) {
                                $body = str_replace($k, $v, $body);
                            }
                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }
                            $document = Document::create([
                                'document_template_id' => 15,
                                'model_type' => JobCard::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);
                            $record->save();
                            return view('filament-docs::print', ['record' => $document]);
                        }),
                    Tables\Actions\Action::make('generateAndPrintJobCard')
                        ->label('Print Job Card')
                        ->color('warning')
                        ->icon('heroicon-s-printer')
                        ->tooltip('Print Job Card')
                        ->action(function ($record, $livewire) {
                            $template = DocumentTemplate::find(15);
                            $templateBody = (string) ($template->body ?? '');
                            $check = $record->check_list ?? [];
                            $map = [
                                '$DOCUMENT_DATE' => now()->format('d-m-Y'),
                                '$JOB_ID' => $record->job_id,
                                '$COMPLAIN_ID' => $record->complain->complain_id ?? '',
                                '$CUSTOMER_NAME' => $record->complain->name ?? '',
                                '$CUSTOMER_PHONE' => $record->complain->mobile ?? '',
                                '$DEVICE' => $record->complain->device ?? '',
                                '$SERVICES' => implode(', ', $record->complain->service_type ?? []),
                                '$ESTIMATE_REPAIR_AMOUNT' => $record->complain->estimate_repair_amount ?? '',
                                '$ESTIMATE_NEW_AMOUNT' => $record->complain->estimate_new_amount ?? '',
                                '$REMOTE' => in_array('Remote', $check) ? '✔' : '✘',
                                '$REMOTE_BATTERY' => in_array('Remote Battery', $check) ? '✔' : '✘',
                                '$ADAPTER' => in_array('Adapter', $check) ? '✔' : '✘',
                                '$POWERCABLE' => in_array('Power Cable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wall Stand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table Stand', $check) ? '✔' : '✘',
                                '$BOX' => in_array('Box', $check) ? '✔' : '✘',
                            ];
                            uksort($map, fn($a, $b) => strlen($b) - strlen($a));
                            $body = $templateBody;
                            foreach ($map as $k => $v) {
                                $body = str_replace($k, $v, $body);
                            }
                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }
                            $document = Document::create([
                                'document_template_id' => 15,
                                'model_type' => JobCard::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);
                            $record->save();
                            $url = PrintDocument::getUrl(['record' => $document->id]);
                            $livewire->js(<<<JS
                                const iframe = document.createElement('iframe');
                                iframe.style.cssText = 'position:absolute;width:0;height:0;border:0';
                                iframe.src = "{$url}";
                                document.body.appendChild(iframe);
                                iframe.onload = () => { iframe.contentWindow.focus(); iframe.contentWindow.print(); };
                            JS);
                        }),
                    Tables\Actions\Action::make('shareJobCard')
                        ->label('Share Job Card')
                        ->color('success')
                        ->icon('heroicon-s-share')
                        ->tooltip('Share Job Card')
                        ->action(function ($record, $livewire) {
                            $template = DocumentTemplate::find(15);
                            $body = $template->body;
                            $check = $record->check_list ?? [];
                            $map = [
                                '$DOCUMENT_DATE' => now()->format('d-m-Y'),
                                '$JOB_ID' => $record->job_id,
                                '$COMPLAIN_ID' => $record->complain->complain_id ?? '',
                                '$CUSTOMER_NAME' => $record->complain->name ?? '',
                                '$CUSTOMER_PHONE' => $record->complain->mobile ?? '',
                                '$DEVICE' => $record->complain->device ?? '',
                                '$SERVICES' => implode(', ', $record->complain->service_type ?? []),
                                '$ESTIMATE_REPAIR_AMOUNT' => $record->complain->estimate_repair_amount ?? '',
                                '$ESTIMATE_NEW_AMOUNT' => $record->complain->estimate_new_amount ?? '',
                                '$REMOTE' => in_array('Remote', $check) ? '✔' : '✘',
                                '$REMOTE_BATTERY' => in_array('Remote Battery', $check) ? '✔' : '✘',
                                '$ADAPTER' => in_array('Adapter', $check) ? '✔' : '✘',
                                '$POWERCABLE' => in_array('Power Cable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wall Stand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table Stand', $check) ? '✔' : '✘',
                                '$BOX' => in_array('Box', $check) ? '✔' : '✘',
                            ];
                            foreach ($map as $k => $v) {
                                $body = str_replace($k, $v, $body);
                            }
                            $header = view('filament.header')->render();
                            $appUrl = config('app.url');
                            $fontBase = rtrim($appUrl, '/') . '/fonts';
                            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8">
<style>
@font-face { font-family:'Devanagari'; src:url('{$fontBase}/NotoSansDevanagari-Regular.ttf') format('truetype'); }
@font-face { font-family:'Lexend'; src:url('{$fontBase}/Lexend-Regular.ttf') format('truetype'); }
html,body { margin:0;padding:0;width:100%;font-family:'Devanagari','Lexend',Arial,sans-serif;font-size:14px;line-height:1.35; }
@page { margin:0; }
.pdf-header { margin:0;padding:0;width:100%;text-align:center; }
.pdf-body { width:100%;padding-left:15px;padding-right:15px;margin:0;box-sizing:border-box;max-width:950px;overflow:hidden;transform-origin:top left;transform:scale(0.96);margin-top:-100px; }
</style></head>
<body>
<div class="pdf-header">{$header}</div>
<div class="pdf-body">{$body}</div>
</body></html>
HTML;
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4');
                            $pdf->getDomPDF()->set_option('isRemoteEnabled', true);
                            $pdf->getDomPDF()->set_option('isHtml5ParserEnabled', true);
                            $pdf->getDomPDF()->set_option('isUnicodeEnabled', true);
                            $fileName = "job-card-{$record->job_id}.pdf";
                            $filePath = "job-cards/{$fileName}";
                            Storage::disk('public')->put($filePath, $pdf->output());
                            $fullUrl = url("storage/{$filePath}");
                            $livewire->js(<<<JS
                                if (navigator.share && navigator.canShare) {
                                    fetch("{$fullUrl}").then(r => r.blob()).then(blob => {
                                        const file = new File([blob], "{$fileName}", { type:"application/pdf" });
                                        if (navigator.canShare({ files:[file] })) {
                                            navigator.share({ title:"Job Card", text:"Job Card {$record->job_id}", files:[file] });
                                        } else { window.open("{$fullUrl}", "_blank"); }
                                    });
                                } else { window.open("{$fullUrl}", "_blank"); }
                            JS);
                        }),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                    Tables\Actions\Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-eye')
                        ->slideOver()
                        ->modalHeading('Job Card Details')
                        ->infolist([
                            \Filament\Infolists\Components\Section::make('Customer')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('complain.complain_id')
                                        ->label('Complain ID'),

                                    \Filament\Infolists\Components\TextEntry::make('complain.name')
                                        ->label('Customer'),

                                    \Filament\Infolists\Components\TextEntry::make('complain.mobile')
                                        ->label('Phone'),
                                ]),

                            \Filament\Infolists\Components\Section::make('Incentives')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('incentives')
                                        ->label('Engineer Incentives')
                                        ->state(
                                            fn($record) =>
                                            static::incentiveBreakdown(
                                                $record->incentive_percentages ?? []
                                            )
                                        )
                                        ->columnSpanFull(),
                                ]),

                            \Filament\Infolists\Components\Section::make('Financial')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('amount')
                                        ->money('INR'),

                                    \Filament\Infolists\Components\TextEntry::make('gst_amount')
                                        ->money('INR'),

                                    \Filament\Infolists\Components\TextEntry::make('expense')
                                        ->money('INR'),

                                    \Filament\Infolists\Components\TextEntry::make('lead_incentive_amount')
                                        ->money('INR'),

                                    \Filament\Infolists\Components\TextEntry::make('incentive_amount')
                                        ->money('INR'),

                                    \Filament\Infolists\Components\TextEntry::make('bright_electronics_profit')
                                        ->money('INR'),
                                ]),
                        ])
                ])->dropdown()->tooltip('Actions')
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
                    ->label('Export')
                    ->exports([
                        ExcelExport::make('job-cards')
                            ->fromTable()
                            ->withFilename(fn() => 'job-cards-' . now()->format('Y-m-d-His'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX),
                    ]),
            ]);
    }


    protected static function assignedEngineersText(?array $ids): string
    {
        if (empty($ids)) {
            return '-';
        }

        return User::whereIn('id', $ids)->pluck('name')->implode(', ');
    }

    protected static function checklistText(?array $items): string
    {
        if (empty($items)) {
            return '-';
        }

        return implode(', ', $items);
    }

    protected static function incentiveBreakdown(?array $rows): string
    {
        if (empty($rows)) {
            return '-';
        }

        return collect($rows)->map(function ($row) {
            $user = User::find($row['user_id'] ?? null);

            return trim(
                ($user?->name ?? 'Unknown') .
                ' | ' .
                number_format((float) ($row['percent'] ?? 0), 2) . '% | ₹' .
                number_format((float) ($row['amount'] ?? 0), 2)
            );
        })->implode(' ; ');
    }

    protected static function sparePartsText(?array $rows): string
    {
        if (empty($rows)) {
            return '-';
        }

        return collect($rows)->map(function ($row) {
            $product = Product::find($row['product_id'] ?? null);

            return trim(
                ($product?->name ?? 'Unknown Product') .
                ' | Qty: ' . (int) ($row['qty'] ?? 1)
            );
        })->implode(' ; ');
    }

    protected static function getProductLabel(?int $productId): string
    {
        $product = Product::with('category.parent')->find($productId);

        if (!$product) {
            return 'Unknown Product';
        }

        $category = $product->category;

        $mainCategory = '-';
        $subCategory = '-';

        if ($category) {
            if ($category->parent_id) {
                $mainCategory = $category->parent?->name ?? '-';
                $subCategory = $category->name;
            } else {
                $mainCategory = $category->name;
            }
        }

        return "{$product->name} ({$product->barcode})
                     -------------
                     Category: {$mainCategory}
                     -------------
                    Sub Category: {$subCategory}";
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ComplainRelationManagerResource\RelationManagers\JobCardResourceRelationManager::class,
        ];
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
        $user = Auth::user();
        return parent::getEloquentQuery()
            ->with(['complain.leadSource'])
            ->when(
                $user &&
                !$user->hasRole(['Administrator', 'Developer', 'admin']) &&
                $user->email !== 'vipprow@gmail.com',
                fn($query) => $query->whereHas('complain', fn($q) => $q->whereJsonContains('assigned_engineers', $user->id))
            );
    }
}