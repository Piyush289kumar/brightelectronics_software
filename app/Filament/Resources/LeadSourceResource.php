<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadSourceResource\Pages;
use App\Filament\Resources\LeadSourceResource\RelationManagers;
use App\Models\LeadSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class LeadSourceResource extends Resource
{
    protected static ?string $model = LeadSource::class;


    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Complains & Jobs';
    protected static ?string $pluralLabel = 'Lead Sources';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Primary Information')
                    ->schema([
                        Forms\Components\TextInput::make('lead_name')
                            ->label('Lead Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('lead_email')
                            ->label('Lead Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('lead_phone_number')
                            ->label('Lead Phone Number')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\Select::make('lead_status')
                            ->label('Lead Status')
                            ->options([
                                'active' => 'Active',
                                'disabled' => 'Disabled',
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\TextInput::make('lead_type')
                            ->label('Lead Type')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('lead_incentive')
                            ->label('Lead Incentive (%)')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Marketing Details')
                    ->schema([
                        Forms\Components\TextInput::make('campaign_name')
                            ->label('Campaign Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('keyword')
                            ->label('Keyword')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('landing_page_url')
                            ->label('Landing Page URL')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('utm_source')
                            ->label('UTM Source')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('utm_medium')
                            ->label('UTM Medium')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('utm_campaign')
                            ->label('UTM Campaign')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('note')
                            ->label('Note (If Any)')
                            ->rows(3),
                        Forms\Components\Textarea::make('other')
                            ->label('Other')
                            ->rows(3),
                    ])
                    ->collapsible(),

            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lead_name')
                    ->label('Lead Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lead_email')
                    ->label('Lead Email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lead_phone_number')
                    ->label('Phone Number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lead_status')
                    ->label('Status')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lead_type')
                    ->label('Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lead_incentive')
                    ->label('Incentive (%)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Campaign'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
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
            'index' => Pages\ListLeadSources::route('/'),
            // 'create' => Pages\CreateLeadSource::route('/create'),
            // 'edit' => Pages\EditLeadSource::route('/{record}/edit'),
        ];
    }
}
