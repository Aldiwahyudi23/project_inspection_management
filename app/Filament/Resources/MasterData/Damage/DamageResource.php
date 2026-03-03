<?php

namespace App\Filament\Resources\MasterData\Damage;

use App\Filament\Resources\MasterData\Damage\DamageResource\Pages;
use App\Filament\Resources\MasterData\Damage\DamageResource\RelationManagers;
use App\Models\MasterData\Damage\Damages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DamageResource extends Resource
{
    protected static ?string $model = Damages::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Damage';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->helperText('Nama kerusakan yang akan ditampilkan di app')
                    ->maxLength(100),

                Forms\Components\TextInput::make('value')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->helperText('isi yang akan di di tampilkan di laporan/label yang sudah di jelaskan'),

                Forms\Components\Textarea::make('handling')
                ->helperText('Instruksi penanganan kerusakan, yang akan di tampilkan di lapoaran')
                    ->rows(3),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Is Active')
                    ->helperText('Aktifkan atau nonaktifkan kerusakan ini')
                    ->inline(false),
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
            RelationManagers\DamagePriceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDamages::route('/'),
            // 'create' => Pages\CreateDamage::route('/create'),
            'view' => Pages\ViewDamages::route('/{record}'),
            'edit' => Pages\EditDamage::route('/{record}/edit'),
        ];
    }
}
