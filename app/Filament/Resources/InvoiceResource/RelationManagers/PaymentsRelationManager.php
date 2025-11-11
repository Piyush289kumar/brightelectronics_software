<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Carbon;
use TomatoPHP\FilamentDocs\Filament\Resources\DocumentResource\Pages\PrintDocument;
use TomatoPHP\FilamentDocs\Models\Document;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments'; // matches Invoice::payments() relation

    protected static ?string $recordTitleAttribute = 'amount';

    // ❌ Remove static

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('amount')
                    ->label('Amount Received')
                    ->numeric()
                    ->required()
                    ->default(fn() => number_format($this->ownerRecord?->balance ?? 0, 2, '.', ''))
                    ->maxValue(fn() => number_format($this->ownerRecord?->balance ?? 0, 2, '.', ''))
                    ->step(0.01), // allows only 2 decimal places

                Forms\Components\DatePicker::make('payment_date')
                    ->label('Payment Date')
                    ->default(now())
                    ->required(),

                Forms\Components\Select::make('method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'bank' => 'Bank Transfer',
                        'upi' => 'UPI',
                        'cheque' => 'Cheque',
                        'card' => 'Card',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('reference_no')
                    ->label('Reference No.'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes'),
            ]);
    }

    // ❌ Remove static
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')->money('INR'),
                Tables\Columns\TextColumn::make('payment_date')->date(),
                Tables\Columns\TextColumn::make('method')->label('Payment Method'),
                Tables\Columns\TextColumn::make('reference_no'),
                Tables\Columns\TextColumn::make('status'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Receive Payment'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

                    // 👇 Generate (or regenerate) and then View Receipt
                    Action::make('generateAndViewDocument')
                        ->label('Preview')
                        ->color('info')
                        ->icon('heroicon-s-eye')
                        ->modalHeading('View Payment Receipt')
                        ->modalButton('Print')
                        ->action(function ($record, $livewire) {
                            $url = PrintDocument::getUrl(['record' => $record->id, 'type' => 'payment']);
                            $livewire->js(<<<JS
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.src = "{$url}";
            document.body.appendChild(iframe);
            iframe.onload = function() {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            };
        JS);
                        })
                        ->modalContent(function ($record) {

                            $template = DocumentTemplate::find(13); // payment receipt template id
                            $templateBody = (string) ($template->body ?? '');

                            $map = [
                                '$NUMBER' => (string) $record->id,
                                '$DOCUMENT_DATE' => Carbon::parse($record->payment_date)->format('d-m-Y'),
                                '$ACCOUNT_NAME' => (string) ($record->invoice?->billable->name ?? ''),
                                '$ACCOUNT_ADDRESS' => (string) ($record->invoice?->billable->address ?? ''),
                                '$ACCOUNT_PHONE' => (string) ($record->invoice?->billable->phone ?? ''),
                                '$ACCOUNT_GSTIN' => (string) ($record->invoice?->billable->gst_number ?? ''),
                                '$ACCOUNT_STATE' => (string) ($record->invoice?->billable->state ?? ''),
                                '$AMOUNT_RECEIVED' => number_format($record->amount, 2),
                                '$AMOUNT_IN_WORDS' => \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($record->amount),
                            ];

                            foreach ($map as $key => $value) {
                                $templateBody = str_replace($key, $value, $templateBody);
                            }

                            // just create document
                            $document = Document::create([
                                'document_template_id' => 13,
                                'model_type' => Payment::class,
                                'model_id' => $record->id,
                                'body' => $templateBody,
                            ]);

                            return view('filament-docs::print', ['record' => $document]);
                        })
                        ->tooltip('Preview Receipt'),

                    // ✅ Generate and then Print (with print preview)
                    Tables\Actions\Action::make('generateAndPrintDocument')
                        ->label('Print')
                        ->color('warning')
                        ->icon('heroicon-s-printer')
                        ->tooltip('Print Document')
                        ->action(function ($record, $livewire) {
                            $template = DocumentTemplate::find(13); // payment receipt template id
                            $templateBody = (string) ($template->body ?? '');

                            $map = [
                                '$NUMBER' => (string) $record->id,
                                '$DOCUMENT_DATE' => Carbon::parse($record->payment_date)->format('d-m-Y'),
                                '$ACCOUNT_NAME' => (string) ($record->invoice?->billable->name ?? ''),
                                '$ACCOUNT_ADDRESS' => (string) ($record->invoice?->billable->address ?? ''),
                                '$ACCOUNT_PHONE' => (string) ($record->invoice?->billable->phone ?? ''),
                                '$ACCOUNT_GSTIN' => (string) ($record->invoice?->billable->gst_number ?? ''),
                                '$ACCOUNT_STATE' => (string) ($record->invoice?->billable->state ?? ''),
                                '$AMOUNT_RECEIVED' => number_format($record->amount, 2),
                                '$AMOUNT_IN_WORDS' => \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($record->amount),
                            ];

                            foreach ($map as $key => $value) {
                                $templateBody = str_replace($key, $value, $templateBody);
                            }

                            // just create document
                            $document = Document::create([
                                'document_template_id' => 13,
                                'model_type' => Payment::class,
                                'model_id' => $record->id,
                                'body' => $templateBody,
                            ]);

                            // 7) Trigger print preview with hidden iframe
                            $url = PrintDocument::getUrl(['record' => $document->id]);
                            $livewire->js(<<<JS
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.src = "{$url}";
            document.body.appendChild(iframe);
            iframe.onload = function() {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            };
        JS);
                        })
                        ->tooltip('Print'),


                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->dropdown()->tooltip('More actions')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
