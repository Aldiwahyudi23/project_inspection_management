<?php

namespace App\Filament\Resources\FormBuilder;

use App\Filament\Resources\FormBuilder\MenuSectionResource\Pages;
use App\Filament\Resources\FormBuilder\MenuSectionResource\RelationManagers;
use App\Models\FormBuilder\MenuSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MenuSectionResource extends Resource
{
    protected static ?string $model = MenuSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                 Forms\Components\TextInput::make('name')
                    ->label('Nama Menu')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('section_type')
                    ->label('Tipe Section')
                    ->options([
                        'menu' => 'Menu',
                        'damage' => 'Kerusakan',
                    ])
                    ->default('menu')
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->inline(false), // Label di atas toggle
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\SectionItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuSections::route('/'),
            // 'create' => Pages\CreateMenuSection::route('/create'),
            'view' => Pages\ViewMenuSection::route('/{record}'),
            'edit' => Pages\EditMenuSection::route('/{record}/edit'),
        ];
    }
}
