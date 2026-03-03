<?php

namespace App\Filament\Resources\Value\InspectionResource\RelationManagers;

use App\Models\MasterData\Damage\Damages;
use App\Models\MasterData\InspectionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InspectionRepairEstimationRelationManager extends RelationManager
{
    protected static string $relationship = 'repairEstimations';

    protected static ?string $title = 'Repair Estimations';

    public function form(Form $form): Form
    {
        return $form->schema([

            /* =====================
             * MAIN INFO
             * ===================== */
            Forms\Components\TextInput::make('part_name')
                ->label('Part / Area')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('repair_description')
                ->label('Repair Description')
                ->rows(3),

            /* =====================
             * STATUS
             * ===================== */
            Forms\Components\Select::make('urgency')
                ->options([
                    'immediate' => 'Segera',
                    'long_term' => 'Jangka Panjang',
                ])
                ->default('immediate')
                ->required(),

            Forms\Components\Select::make('status')
                ->options([
                    'required' => 'Wajib',
                    'recommended' => 'Recommended',
                    'optional' => 'Optional',
                ])
                ->default('required')
                ->required(),

            /* =====================
             * COST
             * ===================== */
            Forms\Components\TextInput::make('estimated_cost')
                ->label('Estimated Cost')
                ->numeric()
                ->prefix('Rp')
                ->default(0),

            /* =====================
             * JSON REFERENCES
             * ===================== */
            Forms\Components\Select::make('related_sources.inspection_items')
                ->label('Related Inspection Items')
                ->multiple()
                ->searchable()
                ->options(
                    InspectionItem::query()
                        ->pluck('name', 'id')
                        ->toArray()
                ),

            Forms\Components\Select::make('related_sources.damages')
                ->label('Related Damages')
                ->multiple()
                ->searchable()
                ->options(
                    Damages::query()
                        ->pluck('label', 'id')
                        ->toArray()
                ),

            /* =====================
             * NOTES
             * ===================== */
            Forms\Components\Textarea::make('notes')
                ->rows(2),

        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('part_name')
            ->columns([

                Tables\Columns\TextColumn::make('part_name')
                    ->label('Part')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) =>
                        Str::limit($record->repair_description, 40)
                    ),

                Tables\Columns\TextColumn::make('urgency')
                    ->badge()
                    ->color(fn (string $state) =>
                        $state === 'immediate' ? 'danger' : 'warning'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Estimation')
                    ->money('IDR', true),

                Tables\Columns\TextColumn::make('related_sources.damages')
                    ->label('Damage Count')
                    ->getStateUsing(fn ($record) =>
                        count($record->related_sources['damages'] ?? [])
                    )
                    ->badge()
                    ->color(fn ($state) =>
                        $state > 0 ? 'success' : 'gray'
                    ),

                // Menghitung jumlah item inspeksi terkait
                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Estimation')
                    ->money('IDR', true)
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('IDR', true)
                    ),

            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
