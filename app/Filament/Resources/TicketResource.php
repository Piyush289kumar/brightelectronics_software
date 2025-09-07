<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Resources\TicketResource\RelationManagers;
use App\Models\Ticket;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Help Desk';
    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('parent_id'),

                Select::make('user_id')
                    ->label('Created By')
                    ->relationship('creator', 'name')
                    ->required()
                    ->default(fn() => auth()->id())
                    ->disabled(fn($record) => $record !== null),

                Select::make('assigned_to')
                    ->label('Assign To')
                    ->options(fn() => User::whereIn('role', ['manager', 'staff'])->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                TextInput::make('ticket_number')
                    ->label('Ticket Number')
                    ->disabled()
                    ->hidden(fn($get) => $get('parent_id') !== null),

                TextInput::make('subject')
                    ->label('Subject')
                    ->required(fn($get) => $get('parent_id') === null)
                    ->visible(fn($get) => $get('parent_id') === null)
                    ->maxLength(255),

                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->required(fn($get) => $get('parent_id') === null)
                    ->visible(fn($get) => $get('parent_id') === null),

                Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->required(fn($get) => $get('parent_id') === null)
                    ->visible(fn($get) => $get('parent_id') === null),

                Textarea::make('message')
                    ->label(fn($get) => $get('parent_id') ? 'Reply Message' : 'Message')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->query(fn(Builder $query) => $query->whereNull('parent_id'))
            ->columns([
                TextColumn::make('ticket_number')->label('Ticket #')->sortable(),
                TextColumn::make('subject')->limit(30)->sortable(),
                BadgeColumn::make('priority')
                    ->colors([
                        'primary' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                    ])
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'primary' => 'open',
                        'warning' => 'in_progress',
                        'success' => 'resolved',
                        'danger' => 'closed',
                    ])
                    ->sortable(),
                TextColumn::make('assignedStaff.name')->label('Assigned To')->sortable(),
                TextColumn::make('creator.name')->label('Created By')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('reply')
                    ->label('Reply')
                    ->form([
                        Textarea::make('message')->label('Reply Message')->required(),
                    ])
                    ->modalHeading(fn(Ticket $record) => "Reply to Ticket #{$record->ticket_number}")
                    ->action(function (Ticket $record, array $data) {
                        Ticket::create([
                            'parent_id' => $record->id,
                            'user_id' => auth()->id(),
                            'message' => $data['message'],
                            'assigned_to' => null,
                            'priority' => null,
                            'status' => null,
                            'subject' => null,
                            'ticket_number' => null,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                ]),
            ]);
    }

    // Generate ticket number automatically before create if root ticket
    public static function beforeCreate(array &$data): void
    {
        if (empty($data['parent_id'])) {
            $data['ticket_number'] = 'TICK-' . strtoupper(Str::random(8));
        }
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
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
