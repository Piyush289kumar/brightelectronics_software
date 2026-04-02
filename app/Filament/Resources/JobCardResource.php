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
                                    'Cancelled' => 'Cancelled',
                                ];
                                if ($user && $user->hasAnyRole(['Administrator', 'admin', 'Manager', 'Team Lead'])) {
                                    $options['Delivered'] = 'Delivered';
                                }
                                return $options;
                            })
                            ->default('Pending')
                            ->required(),
                    ]),
                ])
                ->columns(3)
                ->collapsible(),

            Forms\Components\Section::make('Financials & GST')
                ->schema([

                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->label('Job Amount (₹)')
                        ->reactive()
                        ->live(onBlur: true)
                        ->afterStateUpdated(
                            fn($state, callable $set, callable $get) =>
                            \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)
                        )
                        ->columnSpanFull(),

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
                                    ->prefixIcon('heroicon-o-percent-badge')
                                    ->extraAttributes([
                                        'class' => 'font-semibold text-warning-600'
                                    ]),

                                Forms\Components\TextInput::make('lead_incentive_amount')
                                    ->label('Lead Earnings')
                                    ->prefix('₹')
                                    ->disabled()
                                    ->prefixIcon('heroicon-o-currency-rupee')
                                    ->extraAttributes([
                                        'class' => 'font-bold text-danger-600'
                                    ]),
                            ]),

                        ])
                        ->collapsible()
                        ->collapsed(false)
                        ->columnSpanFull(),

                    // ✅ KEY FIX: Repeater uses ->relationship-style static options
                    // NO getSearchResultsUsing inside live() repeater — that causes closure serialization
                    Forms\Components\Repeater::make('spare_parts')
                        ->label('Spare Parts')
                        ->addActionLabel('Add Spare Part')
                        ->schema([

                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(
                                    \App\Models\Product::all()
                                        ->mapWithKeys(fn($p) => [
                                            $p->id => "{$p->name} ({$p->barcode})"
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

                            Forms\Components\TextInput::make('qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->reactive()
                                ->afterStateUpdated(fn($set, $get) => \App\Filament\Resources\JobCardResource::recalculateAll($set, $get)),

                        ])
                        ->defaultItems(0)
                        ->collapsed()
                        ->columns(2)
                        ->reorderable(false)
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(fn($set, $get) => self::recalculateAll($set, $get)),

                    Grid::make(4)->schema([
                        Forms\Components\TextInput::make('expense')
                            ->label('Product Expense (₹)')
                            ->disabled()
                            ->reactive(),

                        Forms\Components\TextInput::make('gst_amount')
                            ->label('GST Amount (18%)')
                            ->disabled()
                            ->reactive(),

                        Forms\Components\TextInput::make('gross_amount')
                            ->label('Gross After Expense (₹)')
                            ->disabled()
                            ->reactive(),

                        Forms\Components\TextInput::make('bright_electronics_profit')
                            ->label('Profit (₹)')
                            ->disabled()
                            ->reactive(),
                    ]),
                ])
                ->collapsible(),
        ]);
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

                return (float) $productData[$id]->purchase_price * $qty;
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
        $amount = round((float) ($get('amount') ?? 0), 2);

        $products = collect($get('spare_parts') ?? []);

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

        $productData = Product::whereIn('id', $products->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $expense = round(
            $products->sum(function ($row) use ($productData) {
                $product = $productData[$row['product_id']] ?? null;
                return $product ? $product->purchase_price * $row['qty'] : 0;
            }),
            2
        );

        // ✅ GST सिर्फ दिखाने के लिए
        $gstAmount = round(($amount * 18) / 100, 2);

        // ✅ MAIN LOGIC
        $gross = $amount;
        $profit = $gross - $expense;

        // ✅ GET LEAD % FROM COMPLAIN
        $complainId = $get('complain_id');

        $leadPercent = 0;

        if ($complainId) {
            $complain = \App\Models\Complain::with('leadSource')->find($complainId);
            $leadPercent = (float) ($complain?->leadSource?->lead_incentive ?? 0);
        }

        // ✅ LEAD CUT FROM PROFIT
        $leadAmount = round(($profit * $leadPercent) / 100, 2);

        // ✅ FINAL PROFIT
        $finalProfit = $profit - $leadAmount;

        // ✅ SET VALUES
        $set('lead_incentive_percent', $leadPercent);
        $set('lead_incentive_amount', $leadAmount);
        $set('bright_electronics_profit', round($finalProfit, 2));

        $set('expense', $expense);
        $set('gst_amount', $gstAmount);
        $set('gross_amount', $gross);
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
                        'Complete' => 'success',
                        'Return' => 'danger',
                        'Cancelled' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable()
                    ->toggleable(),
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
                Tables\Columns\IconColumn::make('job_verified_by_admin')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->toggleable(),
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
                                '$POWERCABLE' => in_array('Powercable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wallstand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table stand', $check) ? '✔' : '✘',
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
                                '$POWERCABLE' => in_array('Powercable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wallstand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table stand', $check) ? '✔' : '✘',
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
                                '$POWERCABLE' => in_array('Powercable', $check) ? '✔' : '✘',
                                '$WALLSTAND' => in_array('Wallstand', $check) ? '✔' : '✘',
                                '$TABLE_STAND' => in_array('Table stand', $check) ? '✔' : '✘',
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
                ])->dropdown()->tooltip('Actions')
            ])
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
                fn($query) => $query->whereHas('complain', fn($q) => $q->whereJsonContains('assigned_engineers', $user->id))
            );
    }
}