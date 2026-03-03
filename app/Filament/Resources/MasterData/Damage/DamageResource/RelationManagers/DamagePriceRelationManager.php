<?php

namespace App\Filament\Resources\MasterData\Damage\DamageResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DamagePriceRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Repair Prices';

    public function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\TextInput::make('price')
                ->label('Price')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->placeholder('100000'),

            Forms\Components\TextInput::make('unit')
                ->required()
                ->default('panel')
                ->helperText('e.g. panel / item / point / pcs')
                ->placeholder('panel / item / point'),

            Forms\Components\TextInput::make('currency')
                ->default('IDR')
                ->maxLength(10),

            Forms\Components\Select::make('applies_to')
                ->label('Applies To')
                ->options([
                    'body'   => 'Body',
                    'engine' => 'Engine',
                    'interior' => 'Interior',
                    'other'  => 'Other',
                ])
                ->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->columns([

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) =>
                        'Rp ' . number_format($state, 0, ',', '.')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit')
                    ->badge(),

                Tables\Columns\TextColumn::make('applies_to')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'body' => 'success',
                        'engine' => 'warning',
                        'interior' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
