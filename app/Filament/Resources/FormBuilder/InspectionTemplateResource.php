<?php

namespace App\Filament\Resources\FormBuilder;

use App\Filament\Resources\FormBuilder\InspectionTemplateResource\Pages;
use App\Filament\Resources\FormBuilder\InspectionTemplateResource\RelationManagers\MenuSectionsRelationManager;
use App\Models\FormBuilder\InspectionTemplate;
use App\Models\FormBuilder\MenuSection;
use App\Models\FormBuilder\SectionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class InspectionTemplateResource extends Resource
{
    protected static ?string $model = InspectionTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $modelLabel = 'Template Inspeksi';
    protected static ?string $pluralModelLabel = 'Template Inspeksi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Template')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->inline(false),


                Forms\Components\Select::make('settings.menu_model')
                    ->label('Model Menu')
                    ->options([
                        'horizontal' => 'Slide Kesamping',
                        'vertical'   => 'Tombol ke Menu',
                    ])
                    ->required()
                    ->reactive(),

                Forms\Components\Select::make('settings.position')
                    ->label('Posisi Menu')
                    ->options(function (callable $get) {
                        if ($get('settings.menu_model') === 'vertical') {
                            return [
                                'top-left'     => 'Atas Kiri',
                                'top-right'    => 'Atas Kanan',
                                'bottom-left'  => 'Bawah Kiri',
                                'bottom-right' => 'Bawah Kanan',
                            ];
                        }

                        return [
                            'top'    => 'Atas',
                            'bottom' => 'Bawah',
                        ];
                    })
                    ->required()
                    ->reactive(),

                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(500),

                Forms\Components\Hidden::make('sort_order')
                    ->required()
                    ->default(0),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => Str::limit($record->description, 50))
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),

                Tables\Columns\TextColumn::make('menu_sections_count')
                    ->label('Jumlah Menu Section')
                    ->counts('menuSections')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('inspections_count')
                    ->label('Inspeksi Aktif')
                    ->getStateUsing(function (InspectionTemplate $record) {
                        return $record->inspections()
                            ->whereIn('status', ['draft', 'in_progress', 'pending'])
                            ->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->default(true),
                Tables\Filters\Filter::make('has_active_inspections')
                    ->label('Memiliki Inspeksi Aktif')
                    ->query(fn (Builder $query) => 
                        $query->whereHas('inspections', fn ($q) => 
                            $q->whereIn('status', ['draft', 'in_progress', 'pending'])
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplikat')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->action(function (InspectionTemplate $record) {
                        $newTemplate = self::duplicateTemplateWithRelations($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Template berhasil diduplikasi')
                            ->body('Template "' . $record->name . '" telah diduplikasi menjadi "' . $newTemplate->name . '"')
                            ->success()
                            ->send();

                        return redirect(InspectionTemplateResource::getUrl('edit', ['record' => $newTemplate]));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Duplikasi Template')
                    ->modalSubheading(fn (InspectionTemplate $record) => 
                        'Apakah Anda yakin ingin menduplikasi template "' . $record->name . '"? ' .
                        'Template baru akan dibuat dengan:' . PHP_EOL .
                        '• ' . $record->menuSections()->count() . ' Menu Section' . PHP_EOL .
                        '• ' . $record->menuSections()->withCount('sectionItems')->get()->sum('section_items_count') . ' Section Item'
                    )
                    ->modalButton('Duplikat Sekarang'),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, InspectionTemplate $record) {
                        if ($record->hasActiveInspections()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Tidak dapat menghapus')
                                ->body('Template ini memiliki inspeksi aktif. Harap selesaikan atau batalkan inspeksi terlebih dahulu.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),

                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            $templatesWithActiveInspections = $records->filter(function ($record) {
                                return $record->hasActiveInspections();
                            });

                            if ($templatesWithActiveInspections->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak dapat menghapus')
                                    ->body($templatesWithActiveInspections->count() . ' template memiliki inspeksi aktif. Harap selesaikan atau batalkan inspeksi terlebih dahulu.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),

                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),

                    Tables\Actions\BulkAction::make('duplicate')
                        ->label('Duplikat Template Terpilih')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $duplicatedCount = 0;

                            foreach ($records as $originalTemplate) {
                                self::duplicateTemplateWithRelations($originalTemplate);
                                $duplicatedCount++;
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Template berhasil diduplikasi')
                                ->body($duplicatedCount . ' template telah diduplikasi dengan semua relasinya')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Duplikasi Template')
                        ->modalSubheading(fn (Collection $records) => 
                            'Apakah Anda yakin ingin menduplikasi ' . $records->count() . ' template?' . PHP_EOL .
                            'Template baru akan dibuat dengan semua Menu Section dan Section Item-nya.'
                        )
                        ->modalButton('Duplikat Sekarang')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Method untuk menduplikasi template dengan semua relasinya
     */
    protected static function duplicateTemplateWithRelations(InspectionTemplate $originalTemplate): InspectionTemplate
    {
        // 1. Duplikat Template
        $newTemplate = new InspectionTemplate();
        $newTemplate->name = $originalTemplate->name . ' (Copy)';
        $newTemplate->description = $originalTemplate->description;
        $newTemplate->settings = $originalTemplate->settings;
        $newTemplate->sort_order = InspectionTemplate::max('sort_order') + 1;
        $newTemplate->is_active = $originalTemplate->is_active;

        if ($originalTemplate->trashed()) {
            $newTemplate->deleted_at = $originalTemplate->deleted_at;
        }

        $newTemplate->save();

        // 2. Duplikat MenuSections dengan SectionItems
        $originalTemplate->load('menuSections.sectionItems');

        $sectionOrder = 1;
        foreach ($originalTemplate->menuSections as $originalSection) {
            // Duplikat MenuSection
            $newSection = new MenuSection();
            $newSection->template_id = $newTemplate->id;
            $newSection->name = $originalSection->name . ' (Copy)';
            $newSection->section_type = $originalSection->section_type;
            $newSection->is_active = $originalSection->is_active;
            $newSection->sort_order = $sectionOrder++;

            if ($originalSection->trashed()) {
                $newSection->deleted_at = $originalSection->deleted_at;
            }

            $newSection->save();

            // 3. Duplikat SectionItems
            if ($originalSection->sectionItems && $originalSection->sectionItems->isNotEmpty()) {
                $itemOrder = 1;
                foreach ($originalSection->sectionItems as $originalItem) {
                    // Duplikat SectionItem
                    $newItem = new SectionItem();
                    $newItem->section_id = $newSection->id;
                    $newItem->inspection_item_id = $originalItem->inspection_item_id;
                    $newItem->input_type = $originalItem->input_type;
                    $newItem->settings = $originalItem->settings;
                    $newItem->is_active = $originalItem->is_active;
                    $newItem->is_visible = $originalItem->is_visible;
                    $newItem->is_required = $originalItem->is_required;
                    $newItem->sort_order = $itemOrder++;

                    if ($originalItem->trashed()) {
                        $newItem->deleted_at = $originalItem->deleted_at;
                    }

                    $newItem->save();
                }
            }
        }

        return $newTemplate;
    }

    public static function getRelations(): array
    {
        return [
            MenuSectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspectionTemplates::route('/'),
            'view' => Pages\ViewInspectionTemplate::route('/{record}'),
            'edit' => Pages\EditInspectionTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Menambahkan nomor urut untuk setiap baris
     */
    public static function getRowNumber($query, $record): int
    {
        return $query->where('id', '<=', $record->id)->count();
    }
}