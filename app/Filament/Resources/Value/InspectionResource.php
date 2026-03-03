<?php

namespace App\Filament\Resources\Value;

use App\Filament\Resources\Value\InspectionResource\Pages;
use App\Filament\Resources\Value\InspectionResource\RelationManagers;
use App\Models\DirectDB\Vehicle;
use App\Models\Inspection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Value Management';
    
    protected static ?int $navigationSort = 1;

// File: InspectionResource.php (Update form schema)

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vehicle_id')
                    ->label('Vehicle')
                    ->options(
                        Vehicle::query()
                            ->where('is_active', true)
                            ->with(['brand', 'model', 'type', 'transmission'])
                            ->get()
                            ->pluck('display_name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) return;

                        $vehicle = Vehicle::with(['brand', 'model', 'type', 'transmission'])
                            ->find($state);

                        if ($vehicle) {
                            $set('vehicle_name', $vehicle->display_name);
                        }
                    }),

                Forms\Components\TextInput::make('vehicle_name')
                    ->label('Vehicle Name')
                    ->required()
                    ->readOnly(),


                Forms\Components\TextInput::make('license_plate')
                    ->label('License Plate')
                    ->required(),

                Forms\Components\Select::make('template_id')
                    ->label('Inspection Template')
                    ->relationship('template', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('mileage')
                    ->label('Mileage')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('color')
                    ->label('Color'),

                Forms\Components\TextInput::make('chassis_number')
                    ->label('Chassis Number'),

                Forms\Components\TextInput::make('engine_number')
                    ->label('Engine Number'),

                Forms\Components\DateTimePicker::make('inspection_date')
                    ->label('Inspection Date')
                    ->required()
                    ->default(now()),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ])
                    ->default('draft')
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),

                Forms\Components\TextInput::make('inspection_code')
                    ->label('Inspection Code')
                    ->default(fn () => 'INSP-' . strtoupper(Str::random(8)))
                    ->disabled()
                    ->dehydrated(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inspection_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vehicle.vehicle_name')
                    ->label('Vehicle')
                    ->description(fn (Inspection $record): string => 
                        $record->license_plate ?? 'No Plate'
                    )
                    ->searchable(['vehicle_name', 'license_plate'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template')
                    ->sortable(),

                Tables\Columns\TextColumn::make('inspection_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mileage')
                    ->label('Mileage')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'draft',
                        'primary' => 'in_progress',
                        'info' => 'under_review',
                        'success' => ['approved', 'completed'],
                        'danger' => ['cancelled', 'rejected'],
                    ])
                    ->formatStateUsing(fn (string $state): string => 
                        str($state)->replace('_', ' ')->title()
                    ),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state): string => $state . '%')
                    ->color(fn ($state): string => 
                        $state >= 100 ? 'success' : ($state >= 50 ? 'warning' : 'danger')
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\Filter::make('inspection_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => 
                                    $query->whereDate('inspection_date', '>=', $date)
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => 
                                    $query->whereDate('inspection_date', '<=', $date)
                            );
                    }),

                Tables\Filters\SelectFilter::make('template_id')
                    ->label('Template')
                    ->relationship('template', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_vehicle')
                    ->label('Has Vehicle')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('vehicle_id')),
            ])
            ->actions([
                Tables\Actions\Action::make('view_report')
                    ->label('Report')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Inspection $record): string => 
                        route('filament.admin.resources.value.inspections.report', $record)
                    )
                    ->visible(fn (Inspection $record): bool => 
                        $record->isCompleted() || $record->isApproved()
                    ),

                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('inspection_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InspectionResultsRelationManager::class,
            RelationManagers\InspectionImagesRelationManager::class,
            RelationManagers\InspectionRepairEstimationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspections::route('/'),
            'create' => Pages\CreateInspection::route('/create'),
            'edit' => Pages\EditInspection::route('/{record}/edit'),
        ];
    }
}
