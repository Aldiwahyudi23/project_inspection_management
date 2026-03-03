<?php

namespace App\Filament\Resources\MasterData\Damage\DamageCategoryResource\RelationManagers;

use App\Filament\Resources\MasterData\Damage\DamageResource;
use App\Models\MasterData\Damage\Damage;
use App\Models\MasterData\Damage\Damages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class DamageRelationManager extends RelationManager
{
    protected static string $relationship = 'damages';
    protected static ?string $title = 'Damages';

    public function form(Form $form): Form
    {
        return $form->schema([

            /* =====================
             * DAMAGE DATA
             * ===================== */
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

            Forms\Components\Section::make('Default Repair Price')
                ->schema([

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
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) =>
                        Str::limit($record->handling, 50)
                    ),

                Tables\Columns\TextColumn::make('value')
                    ->badge(),

                Tables\Columns\TextColumn::make('prices_count')
                    ->label('Jumlah Harga')
                    ->getStateUsing(fn ($record) => $record->prices->count())
                    ->badge()
                    ->color(fn ($record) =>
                        $record->prices->count() === 0
                            ? 'warning'
                            : 'success'
                    )
                    ->description(fn ($record) =>
                        $record->prices->count() === 0
                            ? 'Belum ada harga'
                            : 'Harga tersedia'
                    ),



                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                // Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (array $data, Damages $record) {

                        // 🔥 SIMPAN KE damage_prices
                        $record->prices()->create([
                            'unit'       => $data['unit'],
                            'price'      => $data['price'],
                            'currency'   => $data['currency'] ?? 'IDR',
                            'applies_to' => $data['applies_to'] ?? null,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make() ->url(fn (Damages $record): string => DamageResource::getUrl('view', ['record' => $record])), 
                Tables\Actions\EditAction::make() ->url(fn (Damages $record): string => DamageResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
