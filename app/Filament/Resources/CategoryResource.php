<?php
namespace App\Filament\Resources;
use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Products & Categories';
    protected static ?int $navigationSort = 16;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Info')->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(50),
                    Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),
                    Forms\Components\Textarea::make('description')->rows(3),
                    Forms\Components\FileUpload::make('image_path')->image()->directory('categories'),
                ])->columns(2),
                Forms\Components\Section::make('Hierarchy & Tax')->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Category')
                        ->relationship('parent', 'name')
                        ->searchable(),
                    Forms\Components\TextInput::make('hsn_code')->maxLength(8),
                    Forms\Components\TextInput::make('default_gst_rate')->numeric()->default(0.00),
                    Forms\Components\Select::make('tax_slab_id')
                        ->relationship('taxSlab', 'name')
                        ->searchable(),
                ])->columns(2),
                Forms\Components\Section::make('Inventory Settings')->schema([
                    Forms\Components\Toggle::make('track_inventory')->default(true),
                    Forms\Components\TextInput::make('default_min_stock')->numeric()->default(0),
                    Forms\Components\TextInput::make('default_max_stock')->numeric(),
                ])->columns(3),
                Forms\Components\Section::make('Display & Status')->schema([
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\KeyValue::make('meta')
                        ->label('Metadata')
                        ->addButtonLabel('Add Meta Key')
                        ->keyLabel('Meta Key')
                        ->valueLabel('Meta Value'),
                ])->columns(3),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('track_inventory')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('default_min_stock')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('taxSlab.name')->label('Tax Slab')->sortable()->searchable()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('hsn_code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('default_gst_rate')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('code')->searchable()->toggleable(),
            ])->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('is_active')
                    ->label('Active Categories')
                    ->query(fn($query) => $query->where('is_active', true)),
                Tables\Filters\Filter::make('track_inventory')
                    ->label('Inventory Tracking Enabled')
                    ->query(fn($query) => $query->where('track_inventory', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                ]),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
