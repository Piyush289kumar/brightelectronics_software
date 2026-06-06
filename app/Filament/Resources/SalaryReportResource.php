<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryReportResource\Pages;
use App\Filament\Resources\SalaryReportResource\RelationManagers;
use App\Models\JobCard;
use App\Models\SalaryReport;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalaryReportResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('basic_salary')
                    ->label('Basic Salary')
                    ->money('INR'),

                Tables\Columns\TextColumn::make('job_count')
                    ->label('Jobs')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        $count = 0;

                        $jobCards = JobCard::where('status', 'Complete')
                            ->whereMonth('created_at', $month)
                            ->get();

                        foreach ($jobCards as $jobCard) {

                            foreach (($jobCard->incentive_percentages ?? []) as $engineer) {

                                if (($engineer['user_id'] ?? null) == $record->id) {
                                    $count++;
                                    break;
                                }
                            }
                        }

                        return $count;
                    }),

                Tables\Columns\TextColumn::make('incentive_total')
                    ->label('Job Card Incentive')
                    ->money('INR')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        $total = 0;

                        $jobCards = JobCard::where('status', 'Complete')
                            ->whereMonth('created_at', $month)
                            ->get();

                        foreach ($jobCards as $jobCard) {

                            foreach (($jobCard->incentive_percentages ?? []) as $engineer) {

                                if (($engineer['user_id'] ?? null) == $record->id) {
                                    $total += (float) ($engineer['amount'] ?? 0);
                                }
                            }
                        }

                        return $total;
                    }),

                Tables\Columns\TextColumn::make('total_salary')
                    ->label('Total Salary')
                    ->money('INR')
                    ->weight('bold')
                    ->color('success')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        $incentive = 0;

                        $jobCards = JobCard::where('status', 'Complete')
                            ->whereMonth('created_at', $month)
                            ->get();

                        foreach ($jobCards as $jobCard) {

                            foreach (($jobCard->incentive_percentages ?? []) as $engineer) {

                                if (($engineer['user_id'] ?? null) == $record->id) {
                                    $incentive += (float) ($engineer['amount'] ?? 0);
                                }
                            }
                        }

                        return ($record->basic_salary ?? 0) + $incentive;
                    }),

            ])
            ->filters([

                Tables\Filters\Filter::make('month')
                    ->form([

                        Forms\Components\Select::make('month')
                            ->label('Month')
                            ->options([
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December',
                            ])
                            ->default(now()->month),

                    ])
                    ->query(fn($query) => $query), // IMPORTANT

            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSalaryReports::route('/'),
        ];
    }
}
