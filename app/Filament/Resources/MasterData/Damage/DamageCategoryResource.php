<?php

namespace App\Filament\Resources\MasterData\Damage;

use App\Filament\Resources\MasterData\Damage\DamageCategoryResource\Pages;
use App\Filament\Resources\MasterData\Damage\DamageCategoryResource\RelationManagers;
use App\Models\MasterData\Damage\DamageCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DamageCategoryResource extends Resource
{
    protected static ?string $model = DamageCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Damage Categories';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('code')
                ->default(function () {
                    $lastCode = DamageCategory::where('code', 'like', 'DM-%')
                        ->orderBy('code', 'desc')
                        ->first();
                    
                    if ($lastCode) {
                        $lastNumber = (int) substr($lastCode->code, 3);
                        $newNumber = $lastNumber + 1;
                        return 'DM-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
                    }
                    
                    return 'DM-001';
                })
                ->disabled() // biar user tidak bisa edit
                ->dehydrated() // tetap disimpan ke database
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),

            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('damages_count')
                    ->counts('damages')
                    ->label('Damages'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DamageRelationManager::class, //Relation to Damages
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDamageCategories::route('/'),
            // 'create' => Pages\CreateDamageCategory::route('/create'),
            'edit' => Pages\EditDamageCategory::route('/{record}/edit'),
        ];
    }
}
