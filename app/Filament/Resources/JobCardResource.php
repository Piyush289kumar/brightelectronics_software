<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobCardResource\Pages;
use App\Filament\Resources\JobCardResource\RelationManagers;
use App\Models\JobCard;
use Filament\Forms;
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
                    ->required(),
                Forms\Components\TextInput::make('job_id')
                    ->label('Job ID')
                    ->required()
                    ->unique(JobCard::class, 'job_id'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Open' => 'Open',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])->default('Open'),
                Forms\Components\TextInput::make('amount')->label('Amount')->numeric(),
                Forms\Components\TextInput::make('gst_amount')->label('GST Amount')->numeric(),
                Forms\Components\TextInput::make('expense')->label('Expense')->numeric(),
                Forms\Components\TextInput::make('gross_amount')->label('Gross Amount')->numeric(),
                Forms\Components\TextInput::make('incentive_type')->label('Incentive Type')->maxLength(255),
                Forms\Components\TextInput::make('incentive_amount')->label('Incentive Amount')->numeric(),
                Forms\Components\TextInput::make('net_profit')->label('Net Profit')->numeric(),
                Forms\Components\TextInput::make('lead_incentive_amount')->label('Lead Incentive Amount')->numeric(),
                Forms\Components\TextInput::make('bright_electronics_profit')->label('Bright Electronics Profit')->numeric(),
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
                Tables\Columns\TextColumn::make('amount')->label('Amount')->money('usd')->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')->label('Gross Amount')->money('usd')->sortable(),
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
            //
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
