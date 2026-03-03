<?php

namespace App\Filament\Resources\Value\InspectionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\InspectionImage;

class InspectionImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Gambar Inspeksi';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('image_path')
                    ->label('Gambar')
                    ->image()
                    ->required()
                    ->directory('inspections')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('note')
                    ->label('Catatan')
                    ->rows(2),

                Forms\Components\Select::make('inspection_item_id')
                    ->label('Item Inspeksi')
                    ->relationship('inspectionItem', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Gambar')
                    ->size(80),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('inspectionItem.name')
                    ->label('Item Inspeksi')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diunggah')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ViewAction::make()
                    ->modalContent(function (InspectionImage $record) {
                        return view('filament.inspection-image-view', [
                            'image' => $record,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}