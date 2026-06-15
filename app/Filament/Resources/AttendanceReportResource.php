<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceReportResource\Pages;
use App\Filament\Resources\AttendanceReportResource\RelationManagers;
use App\Models\AttendanceReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class AttendanceReportResource extends Resource
{
    protected static ?string $model = AttendanceReport::class;


    protected static ?string $navigationLabel = 'Attendance Report';

    protected static ?string $pluralLabel = 'Attendance Reports';

    protected static ?string $modelLabel = 'Attendance Report';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'HR & Payroll';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('Employee Information')
                    ->schema([

                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('from_date')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('month', date('m', strtotime($state)));
                                    $set('year', date('Y', strtotime($state)));
                                }
                            }),

                        Forms\Components\DatePicker::make('to_date')
                            ->required(),

                        Forms\Components\Hidden::make('month'),

                        Forms\Components\Hidden::make('year'),

                    ])
                    ->columns(2),

                Forms\Components\Section::make('Attendance Summary')
                    ->schema([

                        Forms\Components\TextInput::make('working_days')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('present_count')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('absent_count')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('leave_count')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('half_day_count')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('late_punch_count')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('overtime_hours')
                            ->numeric()
                            ->step('0.5')
                            ->default(0),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Documents')
                    ->schema([

                        Forms\Components\FileUpload::make('pdf_file')
                            ->label('Attendance Report PDF')
                            ->disk('public')
                            ->directory('attendance-reports')
                            ->acceptedFileTypes([
                                'application/pdf',
                            ])
                            ->downloadable()
                            ->openable(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Remarks')
                    ->schema([
                        Forms\Components\Textarea::make('remarks')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn($state) => date('F', mktime(0, 0, 0, $state, 1))),

                Tables\Columns\TextColumn::make('year')
                    ->sortable(),

                Tables\Columns\TextColumn::make('working_days')
                    ->label('Working'),

                Tables\Columns\TextColumn::make('present_count')
                    ->label('Present')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('absent_count')
                    ->label('Absent')
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('leave_count')
                    ->label('Leave')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('late_punch_count')
                    ->label('Late')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('overtime_hours')
                    ->label('OT Hrs')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('pdf_file')
                    ->label('PDF')
                    ->boolean()
                    ->getStateUsing(fn($record) => filled($record->pdf_file)),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'submitted',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name'),

                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Super Admin Roles
        if (
            $user->hasRole(['Administrator', 'Developer', 'admin'])
            || $user->email === 'piyushraikwar289@gmail.com'
        ) {
            return $query;
        }

        // Store Manager → All employees of own branch
        if ($user->hasRole('Store Manager')) {
            return $query->where('store_id', $user->store_id);
        }

        // Employee → Only own report
        return $query->where('user_id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceReports::route('/'),
            'create' => Pages\CreateAttendanceReport::route('/create'),
            'edit' => Pages\EditAttendanceReport::route('/{record}/edit'),
        ];
    }
}
