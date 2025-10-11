<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplainRelationManagerResource\RelationManagers\JobCardResourceRelationManager;
use App\Filament\Resources\JobCardResource\Pages;
use App\Filament\Resources\JobCardResource\RelationManagers;
use App\Models\JobCard;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class JobCardResource extends Resource
{
    protected static ?string $model = JobCard::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Complains & Jobs';
    protected static ?string $pluralLabel = 'Job Cards';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('complain_id')
                    ->label('Complain')
                    ->relationship('complain', 'complain_id')
                    ->required()
                    ->disabled()
                    ->dehydrated(true),
                Forms\Components\TextInput::make('job_id')
                    ->label('Job ID')
                    ->required()
                    ->disabled()
                    ->dehydrated(true)
                    ->unique(JobCard::class, 'job_id'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Open' => 'Open',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])->default('Open'),


                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set, $get, $record) {
                        // Calculate GST and Gross
                        $gstAmount = $state * 0.18;
                        $gross = $state; // amount + 18% GST
                        $set('gross_amount', $gross);
                        $set('gst_amount', $gstAmount);

                        // Calculate engineers' incentives
                        $engineers = $get('incentive_percentages') ?? [];
                        $totalStaff = 0;
                        foreach ($engineers as $i => $row) {
                            $percent = isset($row['incentive_type']) && is_numeric($row['incentive_type'])
                                ? (float) $row['incentive_type']
                                : 0;
                            $incentive = ($percent / 100) * $gross;
                            $engineers[$i]['incentive_amount'] = $incentive;
                            $totalStaff += $incentive;
                        }
                        $set('incentive_percentages', $engineers);
                        $set('incentive_amount', $totalStaff);

                        // Net profit after staff incentives
                        $netProfit = $gross - $totalStaff;
                        $set('net_profit', $netProfit);

                        // -------------------------------
                        // Lead Incentive & Bright Electronics Profit
                        // -------------------------------
                        $leadIncentiveAmount = 0;

                        if ($record) {
                            $record->loadMissing('complain.leadSource');

                            if ($record->complain && $record->complain->leadSource) {
                                $leadPercent = is_numeric($record->complain->leadSource->lead_incentive)
                                    ? (float) $record->complain->leadSource->lead_incentive
                                    : 0;

                                $leadIncentiveAmount = ($netProfit * $leadPercent) / 100;
                            }
                        }

                        $set('lead_incentive_amount', $leadIncentiveAmount);
                        $set('bright_electronics_profit', $netProfit - $leadIncentiveAmount);
                    }),


                Forms\Components\TextInput::make('gst_amount')
                    ->label('GST (18%)')
                    ->disabled()
                    ->reactive(),

                Forms\Components\TextInput::make('gross_amount')
                    ->label('Gross Amount')
                    ->disabled()
                    ->reactive(),

                Forms\Components\Repeater::make('incentive_percentages')
                    ->label('Assign Engineer Incentive %')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\Select::make('engineer_id')
                                ->label('Engineer')
                                ->options(function () {
                                    return User::role('Engineer')->pluck('name', 'id')->toArray();
                                })
                                ->required(),

                            Forms\Components\Select::make('incentive_type')
                                ->label('Incentive Type')
                                ->options([
                                    '15% - Pick, Branch Service, and Deliver' => '15',
                                    '5% - Pick and Deliver' => '5',
                                    '10% - Branch Service' => '10',
                                ])
                                ->reactive()
                                ->required(),

                            Forms\Components\TextInput::make('incentive_amount')
                                ->label('Staff Incentive Amount')
                                ->disabled()
                                ->reactive(),
                        ])
                    ])
                    ->columnSpanFull()
                    ->reactive()
                    ->afterStateHydrated(function ($state, $set, $get, $record) {
                        // Only hydrate if state is empty and record has complain assigned engineers
                        if (empty($state) && $record && $record->complain && is_array($record->complain->assigned_engineers)) {
                            $assignedEngineers = $record->complain->assigned_engineers;
                            $newState = [];

                            foreach ($assignedEngineers as $engineerId) {
                                $newState[] = [
                                    'engineer_id' => $engineerId,
                                    'incentive_type' => null, // default value or adjust as needed
                                    'incentive_amount' => 0,   // initial, will be recalculated after
                                ];
                            }

                            $set('incentive_percentages', $newState);
                        }
                    })
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $gross = $get('gross_amount') ?? 0;

                        // Calculate engineers' incentives
                        $engineers = $get('incentive_percentages') ?? [];
                        $totalStaff = 0;
                        foreach ($engineers as $i => $row) {
                            $percent = isset($row['incentive_type']) ? (float) $row['incentive_type'] : 0;
                            $incentive = ($percent / 100) * $gross;
                            $engineers[$i]['incentive_amount'] = $incentive;
                            $totalStaff += $incentive;
                        }
                        $set('incentive_percentages', $engineers);
                        $set('incentive_amount', $totalStaff);

                        // Net profit after staff incentives
                        $netProfit = $gross - $totalStaff;
                        $set('net_profit', $netProfit);

                        // Lead incentive
                        $leadPercent = $get('lead_source_details') ? floatval(preg_replace('/[^0-9.]/', '', $get('lead_source_details'))) : 0;

                        
                        // If you store the lead_incentive_percent separately in the form:
                        $leadPercent = $get('lead_incentive_percent') ?? 0;

                        $leadIncentiveAmount = ($netProfit * $leadPercent) / 100;
                        $set('lead_incentive_amount', $leadIncentiveAmount);
                        $set('bright_electronics_profit', $netProfit - $leadIncentiveAmount);
                    }),



                Forms\Components\TextInput::make('incentive_amount')
                    ->label('Staff Incentive Amount')
                    ->disabled()
                    ->reactive(),


                Forms\Components\TextInput::make('net_profit')
                    ->label('Net Profit')
                    ->disabled(),
                Forms\Components\TextInput::make('lead_source_details')
                    ->label('Lead Source Details')
                    ->disabled()
                    ->afterStateHydrated(function ($state, $set, $get, $record) {
                        if (!$record) {
                            $set('lead_source_details', 'N/A');
                            return;
                        }
                        $record->loadMissing('complain.leadSource');
                        if ($record->complain && $record->complain->leadSource) {
                            $leadName = $record->complain->leadSource->lead_name;
                            $leadIncentive = is_numeric($record->complain->leadSource->lead_incentive)
                                ? (float) $record->complain->leadSource->lead_incentive
                                : 0;
                            $set('lead_source_details', "{$leadName} | {$leadIncentive}%");
                        } else {
                            $set('lead_source_details', 'N/A');
                        }
                    }),

                Forms\Components\TextInput::make('lead_incentive_amount')
                    ->label('Lead Incentive Amount')
                    ->disabled(),

                Forms\Components\TextInput::make('bright_electronics_profit')
                    ->label('Bright Electronics Profit')
                    ->disabled(),


                Forms\Components\TextInput::make('job_verified_by_admin')->label('Job Verified By Admin')->maxLength(255),
                Forms\Components\Textarea::make('note')->label('Note')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('job_id')->label('Job ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('complain.complain_id')->label('Complain ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->sortable(),
                Tables\Columns\TextColumn::make('amount')->label('Amount')->money('inr')->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')->label('Gross Amount')->money('inr')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'Open' => 'Open',
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
            JobCardResourceRelationManager::class,
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
        return parent::getEloquentQuery()->with(['complain.leadSource']);
    }

}
