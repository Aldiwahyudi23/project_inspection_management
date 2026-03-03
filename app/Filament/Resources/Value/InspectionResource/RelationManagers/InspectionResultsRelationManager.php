<?php

namespace App\Filament\Resources\Value\InspectionResource\RelationManagers;

use App\Models\InspectionResult;
use App\Models\MasterData\InspectionItem;
use App\Models\FormBuilder\SectionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InspectionResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'results';

    protected static ?string $title = 'Hasil Inspeksi';

    protected static ?string $modelLabel = 'Hasil Inspeksi';
    
    protected static ?string $pluralModelLabel = 'Hasil-hasil Inspeksi';
    
    protected static ?string $recordTitleAttribute = 'id';

public function form(Form $form): Form
{
    return $form->schema([

        Forms\Components\Select::make('inspection_item_id')
            ->label('Item Inspeksi')
            ->relationship('inspectionItem', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->disabled(fn ($operation) => $operation === 'edit')
            ->live(),

        /* =====================
         * STATUS (SELECT)
         * ===================== */
        Forms\Components\Select::make('status')
            ->label('Status / Nilai')
            ->options(fn (callable $get) => match (
                $this->getInputType($get)
            ) {
                'yes_no' => [
                    'yes' => 'Ya',
                    'no' => 'Tidak',
                    'na' => 'Tidak Berlaku',
                ],
                'rating' => [
                    '1' => '1 - Sangat Buruk',
                    '2' => '2 - Buruk',
                    '3' => '3 - Cukup',
                    '4' => '4 - Baik',
                    '5' => '5 - Sangat Baik',
                ],
                'condition' => [
                    'excellent' => 'Excellent',
                    'good' => 'Good',
                    'fair' => 'Fair',
                    'poor' => 'Poor',
                    'damaged' => 'Damaged',
                ],
                default => [],
            })
            ->required()
            ->visible(fn (callable $get) =>
                in_array($this->getInputType($get), [
                    'yes_no', 'rating', 'condition', 'radio', 'select'
                ])
            ),

        /* =====================
         * STATUS TEXT / NUMBER
         * ===================== */
        Forms\Components\TextInput::make('status')
            ->label('Nilai')
            ->required()
            ->visible(fn (callable $get) =>
                in_array($this->getInputType($get), [
                    'text', 'number', 'date'
                ])
            ),

        /* =====================
         * NOTE
         * ===================== */
        Forms\Components\Textarea::make('note')
            ->label('Catatan')
            ->rows(3)
            ->columnSpanFull()
            ->visible(fn (callable $get) =>
                ! in_array($this->getInputType($get), ['image', 'file'])
            ),

        /* =====================
         * EXTRA DATA
         * ===================== */
        Forms\Components\KeyValue::make('extra_data')
            ->label('Data Tambahan')
            ->columnSpanFull()
            ->visible(fn (callable $get) =>
                in_array($this->getInputType($get), ['key_value', 'custom'])
            ),
    ]);
}

protected function getInputType(callable $get): ?string
{
    if (! $get('inspection_item_id')) {
        return null;
    }

    $item = InspectionItem::with('sectionItems')
        ->find($get('inspection_item_id'));

    return $item?->sectionItem?->input_type;
}


    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inspection_item.name')
            ->columns([
                Tables\Columns\TextColumn::make('inspectionItem.name')
                    ->label('Item Inspeksi')
                    ->searchable()
                    ->sortable()
                    ->description(fn (InspectionResult $record): string => 
                        $record->sectionItem->input_type ?? 'N/A'
                    ),

                Tables\Columns\TextColumn::make('sectionItem.menuSection.name')
                    ->label('Bagian')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        if (is_numeric($state)) {
                            return $state . ' / 5';
                        }
                        return match($state) {
                            'yes' => '✅ Ya',
                            'no' => '❌ Tidak',
                            'na' => '➖ Tidak Berlaku',
                            'excellent' => '⭐ Excellent',
                            'good' => '👍 Good',
                            'fair' => '👌 Fair',
                            'poor' => '👎 Poor',
                            'damaged' => '🚫 Damaged',
                            'completed' => '✅ Completed',
                            'pending' => '⏳ Pending',
                            default => ucfirst($state),
                        };
                    })
                    ->colors([
                        'success' => ['yes', 'completed', 'excellent', 'good', '5', '4'],
                        'warning' => ['fair', 'pending', '3'],
                        'danger' => ['no', 'poor', 'damaged', '1', '2'],
                        'gray' => ['na', 'not_applicable'],
                    ]),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inspectionImages.count')
                    ->label('Jumlah Gambar')
                    ->counts('inspectionImages')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'yes' => 'Ya',
                        'no' => 'Tidak',
                        'na' => 'Tidak Berlaku',
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                    ]),

                Tables\Filters\SelectFilter::make('inspection_item_id')
                    ->label('Item Inspeksi')
                    ->relationship('inspectionItem', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_note')
                    ->label('Memiliki Catatan')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('note')),

                Tables\Filters\Filter::make('has_images')
                    ->label('Memiliki Gambar')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('inspectionImages')
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Hasil')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Handle image upload jika ada
                        if (isset($data['images']) && is_array($data['images'])) {
                            $data['extra_data']['images'] = $data['images'];
                            unset($data['images']);
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Handle image upload jika ada
                        if (isset($data['images']) && is_array($data['images'])) {
                            $data['extra_data']['images'] = $data['images'];
                            unset($data['images']);
                        }
                        return $data;
                    }),

                Tables\Actions\Action::make('view_images')
                    ->label('Lihat Gambar')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Gambar Inspeksi')
                    ->modalContent(function (InspectionResult $record) {
                        $images = $record->inspectionImages;
                        if ($images->isEmpty()) {
                            return new \Illuminate\Support\HtmlString('<p class="p-4 text-center text-gray-500">Tidak ada gambar</p>');
                        }
                        
                        $html = '<div class="grid grid-cols-2 gap-4 p-4">';
                        foreach ($images as $image) {
                            $html .= '<div class="border rounded-lg overflow-hidden">';
                            $html .= '<img src="' . $image->image_url . '" class="w-full h-48 object-cover">';
                            $html .= '<p class="p-2 text-sm text-center text-gray-600">' . ($image->note ?? '') . '</p>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (InspectionResult $record): bool => $record->inspectionImages()->exists()),

                Tables\Actions\DeleteAction::make()
                    ->label('Hapus'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->emptyStateHeading('Belum ada hasil inspeksi')
            ->emptyStateDescription('Tambahkan hasil inspeksi untuk item-item yang telah diperiksa.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Hasil Inspeksi')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    protected function canCreate(): bool
    {
        $inspection = $this->getOwnerRecord();
        return $inspection->canBeEdited();
    }

    protected function canEdit(Model $record): bool
    {
        $inspection = $this->getOwnerRecord();
        return $inspection->canBeEdited();
    }

    protected function canDelete(Model $record): bool
    {
        $inspection = $this->getOwnerRecord();
        return $inspection->canBeEdited();
    }
}