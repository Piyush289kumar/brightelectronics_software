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
                                'Pending' => 'Pending',
                                'Complete' => 'Complete',
                                'Delivered' => 'Delivered',
                                'Return' => 'Return',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Pending')
                            ->columnSpan(1),
                        Forms\Components\CheckboxList::make('check_list')
                            ->label('Check List')
                            ->options([
                                'Remote' => 'Remote',
                                'Remote Battery' => 'Remote Battery',
                                'Adapter' => 'Adapter',
                                'Powercable' => 'Powercable',
                                'Wallstand' => 'Wallstand',
                                'Table stand' => 'Table stand',
                                'Box' => 'Box',
                            ])
                            ->columnSpanFull() // take full width
                            ->columns(7)       // one column per item (makes them line up horizontally)
                            ->extraAttributes([
                                'class' => 'flex flex-row flex-wrap items-center gap-4' // forces horizontal layout and wraps on small screens
                            ])
                            ->helperText('Select all items received/checked')
                            ->default([])
                            ->nullable(),
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
                            ->label('Select Spare Parts (Expenses)')
                            ->multiple()             // multiple select
                            ->reactive()
                            ->searchable()
                            // Search endpoint — returns only in-stock matches
                            ->getSearchResultsUsing(function (string $search) {

                                // 1. Find matching products by search
                                $matches = Product::query()
                                    ->with(['linkedProducts', 'linkedByProducts'])
                                    ->withSum('storeInventories as stock_qty', 'quantity')
                                    ->where(function ($q) use ($search) {
                                    $q->where('name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%")
                                        ->orWhere('barcode', 'like', "%{$search}%");
                                })
                                    ->get();

                                // 2. Also include all linked products
                                $allProducts = collect();

                                foreach ($matches as $product) {
                                    $allProducts->push($product);

                                    // Add linked ones
                                    $allProducts = $allProducts
                                        ->merge($product->allLinkedProducts());
                                }

                                // 3. Remove duplicates
                                $allProducts = $allProducts->unique('id');

                                // 4. Only show items in stock
                                $allProducts = $allProducts->filter(function ($product) {
                                    return ($product->stock_qty ?? 0) > 0;
                                });

                                // 5. Return formatted results
                                return $allProducts
                                    ->mapWithKeys(function ($product) {
                                    $stock = $product->stock_qty ?? 0;
                                    return [$product->id => "{$product->name} — Stock: {$stock}"];
                                })
                                    ->toArray();
                            })

                            // Options shown when the select is opened — only in-stock products
                            ->options(function () {
                                return Product::query()
                                    ->withSum('storeInventories as stock_qty', 'quantity')
                                    ->get()
                                    ->filter(fn($p) => ($p->stock_qty ?? 0) > 0)
                                    ->mapWithKeys(fn($product) => [
                                        $product->id => "{$product->name} — Stock: {$product->stock_qty}",
                                    ])
                                    ->toArray();
                            })

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

                                        Forms\Components\Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'Pending' => 'Pending',
                                                'Complete' => 'Complete',
                                                'Delivered' => 'Delivered',
                                                'Return' => 'Return',
                                                'Cancelled' => 'Cancelled',
                                            ])
                                            ->default('Pending'),

                                        Forms\Components\Toggle::make('job_verified_by_admin')
                                            ->label('Verified by Admin')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->inline(false)
                                            ->helperText('Enable this switch when the job card is verified by admin.')
                                            ->dehydrated()
                                            ->disabled(fn() => !auth()->user()->hasAnyRole(['Administrator', 'Store Manager', 'Team Lead'])),
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
        // Step 1: Product Expense
        $expense = Product::whereIn('id', $productIds)->sum('purchase_price');
        $expense = round($expense, 2);
        // Step 2: GST (not affecting price)
        $gstAmount = round(($amount * 18) / 100, 2);
        // Step 3: Gross = amount - expense
        $gross = round($amount - $expense, 2);
        $set('gst_amount', $gstAmount);
        $set('expense', $expense);
        $set('gross_amount', $gross);
        // Step 4: Lead Incentive
        $leadPercent = round((float) ($get('lead_incentive_percent') ?? 0), 2);
        $leadIncentiveAmount = round(($gross * $leadPercent) / 100, 2);
        $set('lead_incentive_amount', $leadIncentiveAmount);
        // Step 5: Calculate Engineer Incentives sequentially from remaining amount
        $remainingAfterLead = $gross - $leadIncentiveAmount;
        $engineers = $get('incentive_percentages') ?? [];
        $totalEngineerIncentive = 0;
        $remaining = $remainingAfterLead;
        foreach ($engineers as $i => $row) {
            $percent = isset($row['percent']) ? (float) $row['percent'] : 0;
            // incentive based on remaining amount
            $incentiveAmt = round(($remaining * $percent) / 100, 2);
            $engineers[$i]['amount'] = $incentiveAmt;
            // update totals
            $totalEngineerIncentive += $incentiveAmt;
            $remaining -= $incentiveAmt; // deduct this engineer's incentive from remaining
        }
        // Update repeater + totals
        $set('incentive_percentages', $engineers);
        $set('incentive_amount', round($totalEngineerIncentive, 2));
        // Step 6: Final Bright Electronics Profit
        $brightProfit = round($remaining, 2); // remaining after all deductions
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
                        'Pending' => 'warning',
                        'Completed' => 'success',
                        'Return' => 'Return',
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
            ->defaultSort('job_id', 'desc')
            ->striped()
            // ⚙️ Actions
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('toggleVerify')
                        ->label(fn($record) => $record->job_verified_by_admin ? 'Unverify' : 'Verify')
                        ->icon(fn($record) => $record->job_verified_by_admin ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['job_verified_by_admin' => !$record->job_verified_by_admin]);
                        })
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
            iframe.style.position = 'absolute';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.src = "{$url}";
            document.body.appendChild(iframe);
            iframe.onload = function () {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            };
        JS);
                        })
                        ->modalContent(function ($record) {
                            // 1) FETCH TEMPLATE 15
                            $template = DocumentTemplate::find(15);
                            $templateBody = (string) ($template->body ?? '');
                            // 2) BUILD CHECKLIST OUTPUT
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
                                // Checklist ✔ ✘
                                '$REMOTE' => in_array('Remote', $check) ? '✔' : '✘',
                                '$REMOTE_BATTERY' => in_array('Remote Battery', $check) ? '✔' : '✘',
                                '$ADAPTER' => in_array('Adapter', $check) ? '✔' : '✘',
                                '$POWERCABLE' => in_array('Powercable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wallstand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table stand', $check) ? '✔' : '✘',
                                '$BOX' => in_array('Box', $check) ? '✔' : '✘',
                            ];
                            // 3) REPLACE
                            // FIX REPLACE BUG (✔ instead of ✔_BATTERY)
                            uksort($map, fn($a, $b) => strlen($b) - strlen($a));
                            $body = $templateBody;
                            foreach ($map as $k => $v) {
                                $body = str_replace($k, $v, $body);
                            }
                            // 4) DELETE OLD DOCUMENT
                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }
                            // 5) CREATE NEW DOC
                            $document = Document::create([
                                'document_template_id' => 15,
                                'model_type' => JobCard::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);
                            // 6) SAVE DOC ID
                            // $record->document_id = $document->id;
                            $record->save();
                            // 7) RETURN PREVIEW VIEW
                            return view('filament-docs::print', ['record' => $document]);
                        }),
                    Tables\Actions\Action::make('generateAndPrintJobCard')
                        ->label('Print Job Card')
                        ->color('warning')
                        ->icon('heroicon-s-printer')
                        ->tooltip('Print Job Card')
                        ->action(function ($record, $livewire) {
                            // 1) TEMPLATE
                            $template = DocumentTemplate::find(15);
                            $templateBody = (string) ($template->body ?? '');
                            $check = $record->check_list ?? [];
                            // 2) MAP
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
                                '$POWERCABLE' => in_array('Powercable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wallstand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table stand', $check) ? '✔' : '✘',
                                '$BOX' => in_array('Box', $check) ? '✔' : '✘',
                            ];
                            // 3) APPLY MAP
                            // FIX REPLACE BUG (✔ instead of ✔_BATTERY)
                            uksort($map, fn($a, $b) => strlen($b) - strlen($a));
                            $body = $templateBody;
                            foreach ($map as $k => $v) {
                                $body = str_replace($k, $v, $body);
                            }
                            // 4) DELETE OLD DOCUMENT
                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }
                            // 5) CREATE NEW DOC
                            $document = Document::create([
                                'document_template_id' => 15,
                                'model_type' => JobCard::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);
                            // $record->document_id = $document->id;
                            $record->save();
                            // 6) PRINT
                            $url = PrintDocument::getUrl(['record' => $document->id]);
                            $livewire->js(<<<JS
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.src = "{$url}";
            document.body.appendChild(iframe);
            iframe.onload = function () {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            };
        JS);

                        }),



                    Tables\Actions\Action::make('shareJobCard')
                        ->label('Share Job Card')
                        ->color('success')
                        ->icon('heroicon-s-share')
                        ->tooltip('Share Job Card')
                        ->action(function ($record, $livewire) {

                            // 1) Fetch Template Body
                            $template = DocumentTemplate::find(15);
                            $body = $template->body;

                            // Checklist
                            $check = $record->check_list ?? [];

                            // 2) Map Variables
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
                                '$POWERCABLE' => in_array('Powercable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wallstand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table stand', $check) ? '✔' : '✘',
                                '$BOX' => in_array('Box', $check) ? '✔' : '✘',
                            ];

                            foreach ($map as $k => $v) {
                                $body = str_replace($k, $v, $body);
                            }

                            // 3) Load header HTML
                            $header = view('filament.header')->render();

                            // Detect environment
                            $appUrl = config('app.url'); // local OR Hostinger
                            $fontBase = rtrim($appUrl, '/') . '/fonts';

                            // 4) SAFE HTML for DomPDF
                            // 4) SAFE HTML for DomPDF
                            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<style>
@font-face {
    font-family: 'Devanagari';
    src: url('{$fontBase}/NotoSansDevanagari-Regular.ttf') format('truetype');
}

@font-face {
    font-family: 'Lexend';
    src: url('{$fontBase}/Lexend-Regular.ttf') format('truetype');
}


/* -------------------- PAGE SETUP -------------------- */
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    font-family: 'Devanagari', 'Lexend', Arial, sans-serif;
    font-size: 14px;
    line-height: 1.35;
}

/* Force single A4 page */
@page {
    margin: 0;
}

/* -------------------- HEADER -------------------- */
.pdf-header {
    margin: 0;
    padding: 0;
    width: 100%;
    text-align: center;
}

/* -------------------- BODY -------------------- */
.pdf-body {
     width: 100%;
    padding-left: 15px;
    padding-right: 15px;
    margin: 0;                /* No weird shifting */
    box-sizing: border-box;   /* Prevent width overflow */
    max-width: 950px;         /* A4 safe width */
    overflow: hidden;         /* Hide anything going outside */
    transform-origin: top left;
    transform: scale(0.96);   /* Slight shrink to avoid cut edges */
    margin-top: -100px;        /* No top margin */
}

</style>
</head>

<body>

<div class="pdf-header">
    {$header}
</div>

<div class="pdf-body">
    {$body}
</div>

</body>
</html>
HTML;


                            // 5) Generate PDF
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4');
                            $pdf->getDomPDF()->set_option('isRemoteEnabled', true);
                            $pdf->getDomPDF()->set_option('isHtml5ParserEnabled', true);
                            $pdf->getDomPDF()->set_option('isUnicodeEnabled', true);

                            // Save PDF
                            $fileName = "job-card-{$record->job_id}.pdf";
                            $filePath = "job-cards/{$fileName}";
                            Storage::disk('public')->put($filePath, $pdf->output());

                            $fullUrl = url("storage/{$filePath}");

                            // 6) SHARE / OPEN
                            $livewire->js(<<<JS
            if (navigator.share && navigator.canShare) {
                fetch("{$fullUrl}")
                    .then(res => res.blob())
                    .then(blob => {
                        const file = new File([blob], "{$fileName}", { type: "application/pdf" });

                        if (navigator.canShare({ files: [file] })) {
                            navigator.share({
                                title: "Job Card",
                                text: "Job Card {$record->job_id}",
                                files: [file],
                            });
                        } else {
                            window.open("{$fullUrl}", "_blank");
                        }
                    });
            } else {
                window.open("{$fullUrl}", "_blank");
            }
        JS);

                        }),



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
                function ($query) use ($user) {
                    $query->whereHas('complain', function ($q) use ($user) {
                        $q->whereJsonContains('assigned_engineers', $user->id);
                    });
                }
            );
    }

}
