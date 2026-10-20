<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedDataScope;
use App\Filament\Resources\SalaryReportResource\Pages;
use App\Models\AttendanceReport;
use App\Models\JobCard;
use App\Models\User;
use App\Models\UserTarget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Tables\Actions\Action;
use TomatoPHP\FilamentDocs\Models\Document;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;
use NumberFormatter;
use TomatoPHP\FilamentDocs\Filament\Resources\DocumentResource\Pages\PrintDocument;

class SalaryReportResource extends BaseResource
{
    use HasRoleBasedDataScope;
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Salary Report';

    protected static ?string $pluralLabel = 'Salary Reports';

    protected static ?string $modelLabel = 'Salary Report';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'HR & Payroll';

    protected static function permission(string $action): string
    {
        return "{$action}_salary::report";
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                // ==========================================
                // EMPLOYEE
                // ==========================================

                Tables\Columns\TextColumn::make('user_code')
                    ->label('Emp Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                // ==========================================
                // TARGET
                // ==========================================

                Tables\Columns\TextColumn::make('assigned_target')
                    ->label('Assigned Target')
                    ->money('INR')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);

                        return static::getUserTarget(
                            $record->id,
                            $month
                        )?->assigned_amount ?? 0;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_collection')
                    ->label('Total Collection')
                    ->money('INR')
                    ->color('success')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);

                        return static::getUserTarget(
                            $record->id,
                            $month
                        )?->achieved_amount ?? 0;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('remaining_target')
                    ->label('Remaining')
                    ->money('INR')
                    ->color('danger')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);

                        return static::getUserTarget(
                            $record->id,
                            $month
                        )?->remaining_amount ?? 0;
                    })
                    ->toggleable(),

                // ==========================================
                // BASIC SALARY
                // ==========================================

                Tables\Columns\TextColumn::make('basic_salary')
                    ->label('Basic Salary')
                    ->money('INR')
                    ->sortable(),

                // ==========================================
                // JOB COUNT
                // ==========================================

                Tables\Columns\TextColumn::make('job_count')
                    ->label('Jobs')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $count = 0;

                        $jobCards = JobCard::query()
                            ->whereYear('created_at', $year)
                            ->whereMonth('created_at', $month)
                            ->get(['incentive_percentages']);

                        foreach ($jobCards as $jobCard) {

                            foreach (($jobCard->incentive_percentages ?? []) as $engineer) {

                                if ((int) ($engineer['user_id'] ?? 0) === (int) $record->id) {
                                    $count++;
                                    break;
                                }
                            }
                        }

                        return $count;
                    }),

                // ==========================================
                // JOB CARD INCENTIVE
                // ==========================================

                Tables\Columns\TextColumn::make('incentive_total')
                    ->label('Job Card Incentive')
                    ->money('INR')
                    ->color('warning')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        return static::getUserIncentive(
                            $record->id,
                            $month,
                            $year
                        );
                    }),

                // ==========================================
                // GROSS SALARY
                // Basic + Job Card Incentive
                // ==========================================

                Tables\Columns\TextColumn::make('gross_salary')
                    ->label('Gross Salary')
                    ->money('INR')
                    ->weight('bold')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $basicSalary = (float) ($record->basic_salary ?? 0);

                        $incentive = static::getUserIncentive(
                            $record->id,
                            $month,
                            $year
                        );

                        return round(
                            $basicSalary + $incentive,
                            2
                        );
                    }),

                // ==========================================
                // WORKING DAYS
                // ==========================================

                Tables\Columns\TextColumn::make('working_days')
                    ->label('Working Days')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        return static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        )?->working_days ?? 0;
                    }),

                // ==========================================
                // PER DAY SALARY
                // Gross Salary + Allowance / Working Days
                // ==========================================

                Tables\Columns\TextColumn::make('per_day_salary')
                    ->label('Per Day Salary')
                    ->money('INR')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $attendance = static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        );

                        $workingDays = (float) (
                            $attendance?->working_days ?? 0
                        );

                        if ($workingDays <= 0) {
                            return 0;
                        }

                        $basicSalary = (float) (
                            $record->basic_salary ?? 0
                        );

                        $incentive = static::getUserIncentive(
                            $record->id,
                            $month,
                            $year
                        );

                        $grossSalary = $basicSalary + $incentive;

                        return round(
                            $grossSalary / $workingDays,
                            2
                        );
                    }),

                // ==========================================
                // PRESENT
                // ==========================================

                Tables\Columns\TextColumn::make('present_count')
                    ->label('Present')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        return static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        )?->present_count ?? 0;
                    }),

                // ==========================================
                // ABSENT
                // ==========================================

                Tables\Columns\TextColumn::make('absent_count')
                    ->label('Absent')
                    ->color('danger')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        return static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        )?->absent_count ?? 0;
                    }),

                // ==========================================
                // ABSENT DEDUCTION
                // ==========================================

                Tables\Columns\TextColumn::make('absent_deduction')
                    ->label('Absent Deduction')
                    ->money('INR')
                    ->color('danger')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $attendance = static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        );

                        $absentCount = (float) (
                            $attendance?->absent_count ?? 0
                        );

                        $perDaySalary = static::getPerDaySalary(
                            $record,
                            $month,
                            $year
                        );

                        return round(
                            $absentCount * $perDaySalary,
                            2
                        );
                    }),

                // ==========================================
                // LATE PUNCH
                // ==========================================

                Tables\Columns\TextColumn::make('late_punch_count')
                    ->label('Late Punch')
                    ->color('warning')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        return static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        )?->late_punch_count ?? 0;
                    }),

                // ==========================================
                // LATE DEDUCTION
                // 1/4 of daily salary per late punch
                // ==========================================

                Tables\Columns\TextColumn::make('late_deduction')
                    ->label('Late Deduction')
                    ->money('INR')
                    ->color('danger')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $attendance = static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        );

                        $latePunchCount = (float) (
                            $attendance?->late_punch_count ?? 0
                        );

                        $perDaySalary = static::getPerDaySalary(
                            $record,
                            $month,
                            $year
                        );

                        // 1/4 daily salary for every late punch
                        $latePerPunch = $perDaySalary / 4;

                        return round(
                            $latePunchCount * $latePerPunch,
                            2
                        );
                    }),

                // ==========================================
                // TOTAL DEDUCTION
                // ==========================================

                Tables\Columns\TextColumn::make('total_deduction')
                    ->label('Total Deduction')
                    ->money('INR')
                    ->color('danger')
                    ->weight('bold')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $attendance = static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        );

                        $absentCount = (float) (
                            $attendance?->absent_count ?? 0
                        );

                        $latePunchCount = (float) (
                            $attendance?->late_punch_count ?? 0
                        );

                        $perDaySalary = static::getPerDaySalary(
                            $record,
                            $month,
                            $year
                        );

                        $absentDeduction =
                            $absentCount * $perDaySalary;

                        $lateDeduction =
                            $latePunchCount * ($perDaySalary / 4);

                        return round(
                            $absentDeduction + $lateDeduction,
                            2
                        );
                    }),

                // ==========================================
                // ALLOWANCE
                // From users.allowance
                // ==========================================

                Tables\Columns\TextColumn::make('allowance')
                    ->label('Allowance')
                    ->money('INR')
                    ->color('info')
                    ->state(function ($record) {
                        return (float) ($record->allowance ?? 0);
                    }),

                // ==========================================
                // FINAL SALARY
                // Gross - Deduction + Allowance
                // ==========================================

                Tables\Columns\TextColumn::make('total_salary')
                    ->label('Final Salary')
                    ->money('INR')
                    ->weight('bold')
                    ->color('success')
                    ->state(function ($record, $livewire) {

                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        $basicSalary = (float) (
                            $record->basic_salary ?? 0
                        );

                        $incentive = static::getUserIncentive(
                            $record->id,
                            $month,
                            $year
                        );

                        $grossSalary =
                            $basicSalary + $incentive;

                        $attendance = static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        );

                        $workingDays = (float) (
                            $attendance?->working_days ?? 0
                        );

                        if ($workingDays <= 0) {
                            return round(
                                $grossSalary +
                                    (float) ($record->allowance ?? 0),
                                2
                            );
                        }

                        $perDaySalary =
                            $grossSalary / $workingDays;

                        $absentCount = (float) (
                            $attendance?->absent_count ?? 0
                        );

                        $latePunchCount = (float) (
                            $attendance?->late_punch_count ?? 0
                        );

                        $absentDeduction =
                            $perDaySalary * $absentCount;

                        $lateDeduction =
                            ($perDaySalary / 4) *
                            $latePunchCount;

                        $totalDeduction =
                            $absentDeduction +
                            $lateDeduction;

                        $allowance = (float) (
                            $record->allowance ?? 0
                        );

                        return round(
                            $grossSalary
                                - $totalDeduction
                                + $allowance,
                            2
                        );
                    }),

            ])

            ->filters([

                // ==========================================
                // MONTH FILTER
                // ==========================================

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
                            ->default(now()->month)
                            ->live(),

                        Forms\Components\Select::make('year')
                            ->label('Year')
                            ->options(
                                collect(range(
                                    now()->year - 2,
                                    now()->year + 1
                                ))
                                    ->mapWithKeys(
                                        fn($year) => [
                                            $year => $year
                                        ]
                                    )
                                    ->toArray()
                            )
                            ->default(now()->year)
                            ->live(),

                    ])
                    ->query(fn($query) => $query),

            ])
            ->heading(function ($livewire) {
                $month = static::getSelectedMonth($livewire);
                $year = static::getSelectedYear($livewire);

                return 'Salary Report - ' . date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;
            })
            ->actions([
                Action::make('printPayslip')
                    ->label('Print Payslip')
                    ->color('warning')
                    ->icon('heroicon-o-printer')
                    ->tooltip('Generate and print employee salary payslip')

                    ->action(function ($record, $livewire) {

                        // 1. Get selected salary period
                        $month = static::getSelectedMonth($livewire);
                        $year = static::getSelectedYear($livewire);

                        // 2. Load your existing Salary Payslip template
                        $templateId = 14;

                        $template = DocumentTemplate::findOrFail($templateId);
                        $templateBody = (string) $template->body;

                        // 3. Employee salary information
                        $basicSalary = (float) ($record->basic_salary ?? 0);

                        $incentive = static::getUserIncentive(
                            $record->id,
                            $month,
                            $year
                        );

                        $leadIncentive = static::getUserLeadIncentive(
                            $record->id,
                            $month,
                            $year
                        );

                        $grossSalary = round(
                            $basicSalary + $incentive,
                            2
                        );

                        // 4. Attendance information
                        $attendance = static::getAttendance(
                            $record->id,
                            $month,
                            $year
                        );

                        $workingDays = (float) (
                            $attendance?->working_days ?? 0
                        );

                        $presentDays = (float) (
                            $attendance?->present_count ?? 0
                        );

                        $absentDays = (float) (
                            $attendance?->absent_count ?? 0
                        );

                        $latePunches = (float) (
                            $attendance?->late_punch_count ?? 0
                        );

                        // 5. Calculate deductions using your existing salary rules
                        $perDaySalary = $workingDays > 0
                            ? $grossSalary / $workingDays
                            : 0;

                        $absentDeduction = round(
                            $absentDays * $perDaySalary,
                            2
                        );

                        $lateDeduction = round(
                            $latePunches * ($perDaySalary / 4),
                            2
                        );

                        $totalDeduction = round(
                            $absentDeduction + $lateDeduction,
                            2
                        );

                        // 6. Allowance and net salary
                        $allowance = (float) ($record->allowance ?? 0);

                        $netSalary = round($grossSalary - $totalDeduction + $allowance + $leadIncentive, 2);

                        // 7. Salary period
                        $salaryMonth = \Carbon\Carbon::create(
                            $year,
                            $month,
                            1
                        )->format('F Y');

                        // 8. Amount in words
                        $formatter = new NumberFormatter(
                            'en_IN',
                            NumberFormatter::SPELLOUT
                        );

                        $amountInWords = ucfirst(
                            $formatter->format((int) round($netSalary))
                        ) . ' Rupees Only';

                        // 9. Replace placeholders in your template
                        $map = [
                            '$COMPANY_NAME' => config('app.name', 'Bright Electronics'),

                            '$PAYSLIP_NUMBER' => sprintf(
                                'PS-%d-%02d-%4d',
                                $record->id,
                                $month,
                                $year
                            ),

                            '$GENERATED_DATE' => now()->format('d-m-Y'),

                            '$SALARY_MONTH' => $salaryMonth,

                            '$EMPLOYEE_NAME' => (string) $record->name,

                            '$EMPLOYEE_CODE' => (string) (
                                $record->user_code ?? $record->id
                            ),

                            '$EMPLOYEE_EMAIL' => (string) (
                                $record->email ?? ''
                            ),

                            '$WORKING_DAYS' => number_format($workingDays, 0),

                            '$PRESENT_DAYS' => number_format(
                                $presentDays,
                                0
                            ),

                            '$ABSENT_DAYS' => number_format(
                                $absentDays,
                                0
                            ),

                            '$LATE_PUNCHES' => number_format(
                                $latePunches,
                                0
                            ),

                            '$BASIC_SALARY' => number_format(
                                $basicSalary,
                                2
                            ),

                            '$INCENTIVE' => number_format(
                                $incentive,
                                2
                            ),

                            '$ALLOWANCE' => number_format(
                                $allowance,
                                2
                            ),

                            '$LEAD_INCENTIVE' => number_format(
                                $leadIncentive,
                                2
                            ),

                            '$GROSS_EARNINGS' => number_format(
                                $grossSalary + $allowance + $leadIncentive,
                                2
                            ),

                            '$ABSENT_DEDUCTION' => number_format(
                                $absentDeduction,
                                2
                            ),

                            '$LATE_DEDUCTION' => number_format(
                                $lateDeduction,
                                2
                            ),

                            '$TOTAL_DEDUCTION' => number_format(
                                $totalDeduction,
                                2
                            ),

                            '$NET_SALARY' => number_format(
                                $netSalary,
                                2
                            ),

                            '$AMOUNT_IN_WORDS' => $amountInWords,
                        ];

                        // 10. Replace template variables
                        $body = $templateBody;

                        foreach ($map as $key => $value) {
                            $body = str_replace(
                                $key,
                                (string) $value,
                                $body
                            );
                        }

                        // 11. Create the payslip document
                        $document = Document::create([
                            'document_template_id' => $templateId,
                            'model_type' => User::class,
                            'model_id' => $record->id,
                            'body' => $body,
                        ]);

                        // 12. Generate the desired payslip filename
                        $employeeCode = (string) ($record->user_code ?? $record->id);

                        $filename = $employeeCode . ' - ' . strtoupper(
                            \Carbon\Carbon::create($year, $month, 1)->format('M-y')
                        );

                        // 13. Generate the print URL with the filename in the query string
                        $url = PrintDocument::getUrl([
                            'record' => $document->id,
                        ]);

                        $url .= (str_contains($url, '?') ? '&' : '?')
                            . 'payslip_filename=' . urlencode($filename);

                        // 14. Open the print page and trigger printing
                        $livewire->js(<<<JS
    const iframe = document.createElement('iframe');

    iframe.style.position = 'fixed';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';

    iframe.src = "{$url}";
    document.body.appendChild(iframe);

    iframe.onload = function () {
        const printWindow = iframe.contentWindow;

        printWindow.focus();

        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
JS);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
            ])
            ->defaultSort('name');
    }

    // ======================================================
    // HELPER: SELECTED MONTH
    // ======================================================

    protected static function getSelectedMonth($livewire): int
    {
        return (int) data_get(
            $livewire->tableFilters,
            'month.month',
            now()->month
        );
    }

    // ======================================================
    // HELPER: SELECTED YEAR
    // ======================================================

    protected static function getSelectedYear($livewire): int
    {
        return (int) data_get(
            $livewire->tableFilters,
            'month.year',
            now()->year
        );
    }

    // ======================================================
    // ATTENDANCE
    // ======================================================

    protected static function getAttendance(
        int $userId,
        int $month,
        int $year
    ): ?AttendanceReport {

        return AttendanceReport::query()
            ->where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->latest('id')
            ->first();
    }

    // ======================================================
    // PER DAY SALARY
    //
    // Gross Salary / Working Days
    // ======================================================

    protected static function getPerDaySalary(
        $record,
        int $month,
        int $year
    ): float {

        $attendance = static::getAttendance(
            $record->id,
            $month,
            $year
        );

        $workingDays = (float) (
            $attendance?->working_days ?? 0
        );

        if ($workingDays <= 0) {
            return 0;
        }

        $basicSalary = (float) (
            $record->basic_salary ?? 0
        );

        $incentive = static::getUserIncentive(
            $record->id,
            $month,
            $year
        );

        $grossSalary =
            $basicSalary + $incentive;

        return round(
            $grossSalary / $workingDays,
            2
        );
    }

    // ======================================================
    // JOB CARD INCENTIVE
    // ======================================================

    protected static function getUserIncentive(
        int $userId,
        int $month,
        int $year
    ): float {

        $total = 0;

        $jobCards = JobCard::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get([
                'incentive_percentages'
            ]);

        foreach ($jobCards as $jobCard) {

            foreach (
                ($jobCard->incentive_percentages ?? [])
                as $engineer
            ) {

                if (
                    (int) ($engineer['user_id'] ?? 0)
                    === (int) $userId
                ) {
                    $total += (float) (
                        $engineer['amount'] ?? 0
                    );
                }
            }
        }

        return round($total, 2);
    }

    // ==========================================
    // STAFF CALL LEAD INCENTIVE
    // Credit the employee who referred the lead
    // ==========================================

    protected static function getUserLeadIncentive(
        int $userId,
        int $month,
        int $year
    ): float {
        return round(
            JobCard::query()
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereHas('complain', function ($query) use ($userId) {
                    $query
                        ->where('staff_id', $userId)
                        ->whereHas('leadSource', function ($leadQuery) {
                            $leadQuery->where('lead_name', 'STAFF CALL');
                        });
                })
                ->sum('lead_incentive_amount'),
            2
        );
    }

    // ======================================================
    // USER TARGET
    // ======================================================

    protected static function getUserTarget(
        int $userId,
        int $month
    ): ?UserTarget {

        return UserTarget::query()
            ->where('user_id', $userId)
            ->whereHas(
                'storeTarget',
                fn($q) => $q
                    ->where('month', $month)
                    ->where('year', now()->year)
            )
            ->first();
    }

    // ======================================================
    // PERMISSION / USER FILTER
    // ======================================================

    // public static function getEloquentQuery(): Builder
    // {
    //     $user = Auth::user();

    //     // Admin / Manager / Team Lead / Developer
    //     // can see all employees.

    //     if (
    //         $user->hasAnyRole([
    //             'Administrator',
    //             'Developer',
    //             'Manager',
    //             'Team Lead',
    //             'admin'
    //         ])
    //     ) {
    //         return parent::getEloquentQuery();
    //     }

    //     // Engineer / Machine Men
    //     // can see only their own salary.

    //     return parent::getEloquentQuery()
    //         ->where('id', $user->id);
    // }

    public static function getEloquentQuery(): Builder
    {
        return static::applyRoleBasedDataScope(
            parent::getEloquentQuery(),
            Auth::user()
        );
    }

    protected static function applyUserScope(
        Builder $query,
        int $userId
    ): Builder {
        return $query->whereKey($userId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSalaryReports::route('/'),
        ];
    }
}
