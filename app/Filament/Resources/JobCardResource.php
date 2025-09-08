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
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $gstAmount = $state * 0.18;
                        $gross = $state; // amount + 18% GST
                        $set('gross_amount', $gross);
                        $set('gst_amount', $gstAmount);

                        // Calculate total staff incentive from repeater
                        $engineers = $get('incentive_percentages') ?? [];
                        $totalStaff = 0;

                        foreach ($engineers as $i => $row) {
                            $percent = $row['incentive_type'] ?? 0; // percentage selected
                            $incentive = ($percent / 100) * $gross;
                            $engineers[$i]['incentive_amount'] = $incentive;
                            $totalStaff += $incentive;
                        }

                        $set('incentive_percentages', $engineers);
                        $set('incentive_amount', $totalStaff);

                        // Lead incentive (optional)
                        $leadPercent = (float) optional($get('complain')?->leadSource)?->lead_incentive ?? 10;
                        $leadIncentive = ($leadPercent / 100) * $gross;
                        $set('lead_incentive_amount', $leadIncentive);

                        // Net profit and Bright Electronics Profit
                        $netProfit = $gross - $totalStaff - $leadIncentive;
                        $set('net_profit', $netProfit);
                        $set('bright_electronics_profit', $netProfit);
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
                                }),
                            Forms\Components\Select::make('incentive_type')
                                ->label('Incentive Type')
                                ->options([
                                    '15% - Pick, Branch Service, and Deliver' => '15',
                                    '5% - Pick and Deliver' => '5',
                                    '10% - Branch Service' => '10',
                                ])
                                ->reactive(),
                            Forms\Components\TextInput::make('incentive_amount')
                                ->label('Staff Incentive Amount')
                                ->disabled()
                                ->reactive(),
                        ])
                    ])
                    ->columnSpanFull()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $gross = $get('gross_amount') ?? 0;
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

                        $gross = $get('gross_amount') ?? 0;
                        $record = $get('record'); // JobCard record
            
                        $leadPercent = 0;

                        dd($record->complain, $record->complain?->leadSource);
                        if ($record && $record->complain && $record->complain->leadSource) {
                            $leadPercent = (float) $record->complain->leadSource->lead_incentive;
                        }
                        $leadIncentive = ($leadPercent / 100) * $gross;
                        $set('lead_incentive_amount', $leadIncentive);

                        $netProfit = $gross - $totalStaff - $leadIncentive;
                        $set('net_profit', $netProfit);
                        $set('bright_electronics_profit', $netProfit);
                    }),

                Forms\Components\TextInput::make('incentive_amount')
                    ->label('Staff Incentive Amount')
                    ->disabled()
                    ->reactive(),


                Forms\Components\TextInput::make('net_profit')
                    ->label('Net Profit')
                    ->disabled(),

                Forms\Components\TextInput::make('lead_incentive_amount')
                    ->label('Lead Incentive Amount')
                    ->disabled()
                    ->default(function ($get, $record) {
                        // $record is the current JobCard
                        $gross = $get('gross_amount') ?? 0;

                        if ($record && $record->complain && $record->complain->leadSource) {
                            $leadPercent = (float) $record->complain->leadSource->lead_incentive;
                            return ($leadPercent / 100) * $gross;
                        }

                        return 0;
                    })
                    ->reactive()
                    ->afterStateHydrated(function ($state, $set, $get, $record) {
                        $gross = $get('gross_amount') ?? 0;

                        if ($record && $record->complain && $record->complain->leadSource) {
                            $leadPercent = (float) $record->complain->leadSource->lead_incentive;
                            $set('lead_incentive_amount', ($leadPercent / 100) * $gross);
                        }
                    }),


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
}
