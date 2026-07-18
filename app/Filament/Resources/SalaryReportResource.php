<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryReportResource\Pages;
use App\Filament\Resources\SalaryReportResource\RelationManagers;
use App\Models\Complain;
use App\Models\JobCard;
use App\Models\SalaryReport;
use App\Models\User;
use App\Models\UserTarget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class SalaryReportResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Salary Report';

    protected static ?string $pluralLabel = 'Salary Reports';

    protected static ?string $modelLabel = 'Salary Report';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'HR & Payroll';

    public static function canCreate(): bool
    {
        return false;
    }

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

                Tables\Columns\TextColumn::make('assigned_target')
                    ->label('Assigned Target')
                    ->money('INR')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        return static::getUserTarget($record->id, $month)?->assigned_amount ?? 0;
                    }),

                Tables\Columns\TextColumn::make('total_collection')
                    ->label('Total Collection')
                    ->money('INR')
                    ->color('success')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        return static::getUserTarget($record->id, $month)?->achieved_amount ?? 0;
                    }),

                Tables\Columns\TextColumn::make('remaining_target')
                    ->label('Remaining')
                    ->money('INR')
                    ->color('danger')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        return static::getUserTarget($record->id, $month)?->remaining_amount ?? 0;
                    }),

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

                        $jobCards = JobCard::query()
                            // ->where('status', 'Complete')
                            ->whereMonth('created_at', $month)
                            ->get(['incentive_percentages']);

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
                    ->color('warning')
                    ->state(function ($record, $livewire) {

                        $month = data_get(
                            $livewire->tableFilters,
                            'month.month',
                            now()->month
                        );

                        return static::getUserIncentive(
                            $record->id,
                            $month
                        );
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

                        $incentive = static::getUserIncentive(
                            $record->id,
                            $month
                        );

                        return ($record->basic_salary ?? 0)
                            + $incentive;
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
                    ->query(fn($query) => $query),

            ])
            ->defaultSort('name');
    }


    protected static function getUserIncentive(int $userId, int $month): float
    {
        $total = 0;

        $jobCards = JobCard::query()
            // ->where('status', 'Complete')
            ->whereMonth('created_at', $month)
            ->get(['incentive_percentages']);

        foreach ($jobCards as $jobCard) {

            foreach (($jobCard->incentive_percentages ?? []) as $engineer) {

                if (($engineer['user_id'] ?? null) == $userId) {
                    $total += (float) ($engineer['amount'] ?? 0);
                }
            }
        }

        return round($total, 2);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        // Admin, Manager, Team Lead, Developer => see all users
        if (
            $user->hasAnyRole([
                'Administrator',
                'Developer',
                'Manager',
                'Team Lead',
                'admin'
            ])
        ) {
            return parent::getEloquentQuery();
        }

        // Engineer / Machine Men => see only themselves
        return parent::getEloquentQuery()
            ->where('id', $user->id);
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSalaryReports::route('/'),
        ];
    }

    protected static function getUserTarget(int $userId, int $month): ?UserTarget
    {
        return UserTarget::query()
            ->where('user_id', $userId)
            ->whereHas('storeTarget', fn($q) => $q
                ->where('month', $month)
                ->where('year', now()->year))
            ->first();
    }
}
