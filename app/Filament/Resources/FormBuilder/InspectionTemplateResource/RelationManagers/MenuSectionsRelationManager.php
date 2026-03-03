<?php

namespace App\Filament\Resources\FormBuilder\InspectionTemplateResource\RelationManagers;

use App\Filament\Resources\FormBuilder\MenuSectionResource;
use App\Models\FormBuilder\MenuSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class MenuSectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'menuSections';
    
    protected static ?string $title = 'Menu Sections';
    
    protected static ?string $modelLabel = 'Menu Section';
    protected static ?string $pluralModelLabel = 'Menu Sections';

    public function form(Form $form): Form
    {
        // Dapatkan ID dari model induk (InspectionTemplate)
        $ownerRecord = $this->getOwnerRecord();

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Section')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: 'menu_sections',
                        column: 'name',
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule) use ($ownerRecord) {
                            return $rule->where('template_id', $ownerRecord->id);
                        }
                    ),

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
                    ->inline(false),

                Forms\Components\Hidden::make('sort_order')
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Section')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('section_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'menu' => 'success',
                        'damage' => 'warning',
                        default => 'success',
                    }),
                    
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),

                Tables\Columns\TextColumn::make('section_items_count')
                    ->label('Jumlah Item')
                    ->counts('sectionItems')
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();
                        $maxOrder = $ownerRecord
                            ->menuSections()
                            ->max('sort_order');
                        $data['sort_order'] = ($maxOrder ?? 0) + 1;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (MenuSection $record): string => MenuSectionResource::getUrl('view', ['record' => $record])),
                
                Tables\Actions\EditAction::make()
                    ->url(fn (MenuSection $record): string => MenuSectionResource::getUrl('edit', ['record' => $record])),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}